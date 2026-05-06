<?php
/**
 * src/api.php
 * Routage complet - Mise à jour des chemins suite au merge.
 */
session_start();

// Correction des chemins : l'API est dans 'src', les noyaux sont dans '../core'
require_once __DIR__ . '/../core/BaseDeDonnees.php';
require_once __DIR__ . '/../core/GestionnaireAuth.php';
require_once __DIR__ . '/Model/Scenario.php';
require_once __DIR__ . '/Model/Utilisateur.php';

$donnees = json_decode(file_get_contents('php://input'), true) ?? [];
$action = trim($donnees['action'] ?? $_POST['action'] ?? $_GET['action'] ?? '');

if (!$action) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['succes' => false, 'erreur' => 'Action manquante ou JSON invalide.']);
    exit;
}

try {
    $pdo = BaseDeDonnees::obtenirInstance();
    
    // --- ACTIONS PUBLIQUES ---
    if ($action === 'login') {
        $u = $_POST['username'] ?? $donnees['username'] ?? '';
        $p = $_POST['password'] ?? $donnees['password'] ?? '';
        if (GestionnaireAuth::login($u, $p, $pdo)) header('Location: ../index.php?page=tableau-de-bord');
        else header('Location: ../index.php?page=connexion&erreur=auth');
        exit;
    }

    if ($action === 'register') {
        $u = $_POST['username'] ?? $donnees['username'] ?? '';
        $p = $_POST['password'] ?? $donnees['password'] ?? '';
        $mUser = new Utilisateur($pdo);
        if (!$mUser->trouverParNom($u) && $mUser->inscrire($u, $p)) {
            GestionnaireAuth::login($u, $p, $pdo);
            header('Location: ../index.php?page=tableau-de-bord');
        } else header('Location: ../index.php?page=connexion&erreur=reg');
        exit;
    }

    if ($action === 'logout') {
        session_destroy();
        header('Location: ../index.php');
        exit;
    }

    // --- SÉCURITÉ ---
    $idUser = $_SESSION['utilisateur_id'] ?? null;
    if (!$idUser) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(403);
        echo json_encode(['succes' => false, 'erreur' => 'Authentification requise.']);
        exit;
    }

    $modele = new Scenario($pdo);
    header('Content-Type: application/json; charset=utf-8');
    $reponse = ["succes" => false];

    switch ($action) {
        // --- GESTION DU TABLEAU DE BORD ---
        case 'lire_scenarios':
            $reponse = $modele->lireScenariosParUtilisateur($idUser);
            break;

        case 'creer_scenario':
            $nom = $donnees['nom'] ?? 'Nouveau scénario';
            $id = $modele->creerScenario($idUser, $nom);
            $reponse = ["id" => $id, "succes" => true];
            break;

        case 'supprimer_scenario':
            $id = (int)($donnees['id'] ?? 0);
            $reponse = ["succes" => $modele->supprimerScenario($id, $idUser)];
            break;

        // --- GESTION DE L'ÉDITEUR DE TOPOLOGIE ---
        case 'charger_scenario':
            $sid = (int)($donnees['id'] ?? $_GET['id'] ?? 0);
            $s = $modele->obtenirScenario($sid, $idUser);
            if ($s) {
                $s['routeurs'] = $modele->lireRouteurs($sid);
                $s['switchs'] = $modele->lireSwitchsParScenario($sid);
                $s['reseaux'] = $modele->lireSousReseaux($sid); 
                $s['hotes'] = $modele->lireHotesParScenario($sid);
                $s['liaisons_hs'] = $modele->lireLiaisonsHoteSwitch($sid);
                $s['liaisons_is'] = $modele->lireLiaisonsInterfaceSwitch($sid);
                $reponse = $s;
            } else {
                $reponse = ["succes" => false, "erreur" => "Scénario introuvable."];
            }
            break;

        case 'creer_equipement':
            $sid = (int)$donnees['scenario_id'];
            $type = $donnees['type'];
            $nom = $donnees['nom'];
            
            if ($type === 'routeurs') {
                $id = $modele->creerRouteur($sid, $nom);
            } elseif ($type === 'reseaux') {
                $id = $modele->creerSousReseau($sid, $nom, "10.0.0.0/24");
            } else {
                $sr = $modele->lireSousReseaux($sid);
                $srid = empty($sr) ? $modele->creerSousReseau($sid, "LAN_Defaut", "10.0.0.0/24") : $sr[0]['id'];
                $ipAlea = "192.168.1." . rand(10, 250);
                $id = ($type === 'switchs') ? $modele->creerSwitch($srid, $nom) : $modele->creerHote($srid, $nom, $ipAlea);
            }
            $reponse = ["id" => $id, "succes" => true];
            break;

        case 'renommer_equipement':
            $type = $donnees['type'] ?? '';
            $id = (int)($donnees['id'] ?? 0);
            $nouveauNom = $donnees['nom'] ?? '';

            if ($id > 0 && !empty($nouveauNom)) {
                $reponse = ["succes" => $modele->renommerEquipement($type, $id, $nouveauNom)];
            } else {
                $reponse = ["succes" => false, "erreur" => "Données invalides pour le renommage."];
            }
            break;

        case 'mettre_a_jour_positions':
            $p = explode('_', $donnees['id']);
            if (count($p) === 2) {
                $typeEquipement = ($p[0] === 'reseaux') ? 'sous_reseau' : $p[0];
                $reponse = ["succes" => $modele->mettreAJourPositions($typeEquipement, (int)$p[1], (int)$donnees['x'], (int)$donnees['y'])];
            }
            break;

        case 'supprimer_equipement':
            $p = explode('_', $donnees['id']);
            if (count($p) === 2) {
                if ($p[0] === 'routeurs') $table = 'routeur';
                elseif ($p[0] === 'reseaux') $table = 'sous_reseau';
                else $table = substr($p[0], 0, -1);
                
                $reponse = ["succes" => $modele->supprimerEquipement($table, (int)$p[1])];
            }
            break;

        case 'supprimer_liaison':
            $p = explode('_', $donnees['id']);
            $ok = false;
            if (isset($p[0])) {
                if ($p[0] === 'lhs' && count($p) === 3) $ok = $modele->supprimerLiaisonHoteSwitch((int)$p[1], (int)$p[2]);
                elseif ($p[0] === 'lis' && count($p) === 3) $ok = $modele->supprimerLiaisonInterfaceSwitch((int)$p[1], (int)$p[2]);
            }
            $reponse = $ok ? ["succes" => true] : ["succes" => false, "erreur" => "Impossible de supprimer cette liaison."];
            break;

        case 'creer_liaison':
            $f = explode('_', $donnees['from']);
            $t = explode('_', $donnees['to']);
            $ok = false;
            $erreur_msg = "";

            $t1 = $f[0] ?? ''; $id1 = (int)($f[1] ?? 0);
            $t2 = $t[0] ?? ''; $id2 = (int)($t[1] ?? 0);

            try {
                if (($t1 === 'hotes' && $t2 === 'switchs') || ($t1 === 'switchs' && $t2 === 'hotes')) {
                    $hoteId = ($t1 === 'hotes' ? $id1 : $id2);
                    $switchId = ($t1 === 'switchs' ? $id1 : $id2);
                    $ok = $modele->creerLiaisonHoteSwitch($hoteId, $switchId);
                    if (!$ok) $erreur_msg = "Rejeté par la BDD.";
                
                } elseif (($t1 === 'routeurs' && $t2 === 'switchs') || ($t1 === 'switchs' && $t2 === 'routeurs')) {
                    $routeurId = ($t1 === 'routeurs' ? $id1 : $id2);
                    $switchId = ($t1 === 'switchs' ? $id1 : $id2);
                    $intId = $modele->obtenirInterfaceLibre($routeurId);
                    $ok = $modele->creerLiaisonInterfaceSwitch($intId, $switchId);
                    if (!$ok) $erreur_msg = "Rejeté par la BDD.";
                
                } else {
                    $erreur_msg = "Liaison interdite ($t1 ↔ $t2).";
                }
            } catch (Exception $e) {
                $erreur_msg = "Erreur SQL : " . $e->getMessage();
            }

            $reponse = $ok ? ["succes" => true] : ["succes" => false, "erreur" => $erreur_msg];
            break;

        case 'lire_interfaces_routeur':
            $rid = (int)($donnees['id_routeur'] ?? 0);
            $reponse = ["succes" => true, "interfaces" => $modele->lireInterfacesRouteur($rid)];
            break;

        case 'creer_interface_routeur':
            $rid = (int)($donnees['id_routeur'] ?? 0);
            $ip = $donnees['ip'] ?? '';
            $masque = (int)($donnees['masque'] ?? 24);
            $nom = $donnees['nom'] ?? '';
            
            if ($rid > 0 && !empty($ip) && !empty($nom)) {
                $idInt = $modele->creerInterfaceRouteur($rid, $ip, $masque, $nom);
                $reponse = ($idInt > 0) ? ["succes" => true, "id" => $idInt] : ["succes" => false, "erreur" => "IP invalide."];
            } else {
                $reponse = ["succes" => false, "erreur" => "Champs manquants."];
            }
            break;

        case 'supprimer_interface':
            $idInt = (int)($donnees['id_interface'] ?? 0);
            $reponse = ["succes" => $modele->supprimerInterfaceRouteur($idInt)];
            break;

        default:
            $reponse = ["succes" => false, "erreur" => "Action non reconnue."];
            break;
    }

    echo json_encode($reponse);

} catch (Exception $e) {
    echo json_encode(["succes" => false, "erreur" => "Exception API : " . $e->getMessage()]);
}