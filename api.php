<?php
header('Content-Type: application/json');

$host = 'localhost';
$db   = 'LAMP';
$user = 'jules';
$pass = 'root';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => $conn->connect_error]);
    exit;
}

// ---- Ajout d'un équipement ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $fields = ['nom_equipement', 'marque_modele', 'emplacement_physique', 'adresse_ip', 'masque_sous_reseau', 'passerelle', 'type_peripherique', 'etat', 'specification'];
    foreach ($fields as $f) {
        if (!isset($data[$f])) $data[$f] = '';
    }
    $stmt = $conn->prepare("INSERT INTO network_table (nom_equipement, marque_modele, emplacement_physique, adresse_ip, masque_sous_reseau, passerelle, type_peripherique, etat, specification) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssssss",
        $data['nom_equipement'], $data['marque_modele'], $data['emplacement_physique'],
        $data['adresse_ip'], $data['masque_sous_reseau'], $data['passerelle'],
        $data['type_peripherique'], $data['etat'], $data['specification']
    );
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'id' => $conn->insert_id]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => $stmt->error]);
    }
    $conn->close();
    exit;
}

// ---- Modification d'un équipement ----
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID invalide']);
        exit;
    }
    $data = json_decode(file_get_contents('php://input'), true);
    $fields = ['nom_equipement', 'marque_modele', 'emplacement_physique', 'adresse_ip', 'masque_sous_reseau', 'passerelle', 'type_peripherique', 'etat', 'specification'];
    foreach ($fields as $f) {
        if (!isset($data[$f])) $data[$f] = '';
    }
    $stmt = $conn->prepare("UPDATE network_table SET nom_equipement=?, marque_modele=?, emplacement_physique=?, adresse_ip=?, masque_sous_reseau=?, passerelle=?, type_peripherique=?, etat=?, specification=? WHERE id=?");
    $stmt->bind_param("sssssssssi",
        $data['nom_equipement'], $data['marque_modele'], $data['emplacement_physique'],
        $data['adresse_ip'], $data['masque_sous_reseau'], $data['passerelle'],
        $data['type_peripherique'], $data['etat'], $data['specification'], $id
    );
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => $stmt->error]);
    }
    $conn->close();
    exit;
}

// ---- Suppression d'un équipement ----
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID invalide']);
        exit;
    }
    $stmt = $conn->prepare("DELETE FROM network_table WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => $stmt->error]);
    }
    $conn->close();
    exit;
}

$filtre = isset($_GET['type']) ? $_GET['type'] : 'Tous';

if ($filtre === 'Tous') {
    $result = $conn->query("SELECT * FROM network_table ORDER BY type_peripherique, nom_equipement");
} else {
    $stmt = $conn->prepare("SELECT * FROM network_table WHERE type_peripherique = ? ORDER BY nom_equipement");
    $stmt->bind_param("s", $filtre);
    $stmt->execute();
    $result = $stmt->get_result();
}

$stats = $conn->query("SELECT
    COUNT(*) AS total,
    SUM(CASE WHEN UPPER(etat) = 'TRUE' THEN 1 ELSE 0 END) AS en_ligne,
    SUM(CASE WHEN UPPER(etat) != 'TRUE' THEN 1 ELSE 0 END) AS hors_ligne
    FROM network_table")->fetch_assoc();

$rows = [];
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
}

echo json_encode([
    'stats' => $stats,
    'rows'  => $rows,
]);

$conn->close();
