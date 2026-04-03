<?php
// ============================================================
// CONFIGURATION BASE DE DONNÉES
// ============================================================
$host = 'localhost';
$db   = 'LAMP';
$user = 'jules';
$pass = 'root';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Erreur de connexion : " . $conn->connect_error);
}

// ============================================================
// FILTRE PAR TYPE (via l'URL : ?type=Caméra par exemple)
// ============================================================
$filtre = isset($_GET['type']) ? $_GET['type'] : 'Tous';

if ($filtre === 'Tous') {
    $sql = "SELECT * FROM network_table ORDER BY type_peripherique, nom_equipement";
    $result = $conn->query($sql);
} else {
    $stmt = $conn->prepare("SELECT * FROM network_table WHERE type_peripherique = ? ORDER BY nom_equipement");
    $stmt->bind_param("s", $filtre);
    $stmt->execute();
    $result = $stmt->get_result();
}

// Types disponibles pour les boutons de filtre
$types_result = $conn->query("SELECT DISTINCT type_peripherique FROM network_table ORDER BY type_peripherique");

// Stats globales
$stats = $conn->query("SELECT
    COUNT(*) AS total,
    SUM(CASE WHEN UPPER(etat) = 'TRUE' THEN 1 ELSE 0 END) AS en_ligne,
    SUM(CASE WHEN UPPER(etat) != 'TRUE' THEN 1 ELSE 0 END) AS hors_ligne
    FROM network_table")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Network Dashboard</title>
    <style>
        /* ---- Reset & base ---- */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: Inter, 'Helvetica Neue', Arial, sans-serif;
            background-color: #111217;
            color: #d8d9da;
            min-height: 100vh;
            font-size: 14px;
        }

        /* ---- Topbar ---- */
        .topbar {
            background-color: #161719;
            border-bottom: 1px solid #2c3235;
            height: 48px;
            display: flex;
            align-items: center;
            padding: 0 16px;
            gap: 12px;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .topbar-logo {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 16px;
            font-weight: 600;
            color: #ff7800;
            text-decoration: none;
        }

        .topbar-logo svg {
            width: 24px;
            height: 24px;
        }

        .topbar-title {
            color: #d8d9da;
            font-size: 14px;
            font-weight: 500;
            padding-left: 12px;
            border-left: 1px solid #2c3235;
        }

        .topbar-time {
            margin-left: auto;
            color: #6e9fff;
            font-size: 12px;
            background: #1f2937;
            padding: 4px 10px;
            border-radius: 4px;
            border: 1px solid #2c3235;
        }

        /* ---- Contenu principal ---- */
        .main {
            padding: 16px;
            max-width: 1600px;
            margin: 0 auto;
        }

        /* ---- Titre de section ---- */
        .section-title {
            color: #d8d9da;
            font-size: 18px;
            font-weight: 500;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .section-title::before {
            content: '';
            display: inline-block;
            width: 3px;
            height: 18px;
            background: #ff7800;
            border-radius: 2px;
        }

        /* ---- Stat cards ---- */
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 8px;
            margin-bottom: 16px;
        }

        .stat-card {
            background: #181b1f;
            border: 1px solid #2c3235;
            border-radius: 4px;
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .stat-card-label {
            font-size: 12px;
            color: #8e9ba7;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-card-value {
            font-size: 32px;
            font-weight: 300;
            line-height: 1.1;
        }

        .stat-card-value.total   { color: #6e9fff; }
        .stat-card-value.online  { color: #73bf69; }
        .stat-card-value.offline { color: #f2495c; }

        .stat-card-sub {
            font-size: 11px;
            color: #6c737a;
            margin-top: 2px;
        }

        /* ---- Panel ---- */
        .panel {
            background: #181b1f;
            border: 1px solid #2c3235;
            border-radius: 4px;
            overflow: hidden;
        }

        .panel-header {
            padding: 10px 16px;
            border-bottom: 1px solid #2c3235;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #1c2127;
        }

        .panel-title {
            font-size: 13px;
            font-weight: 500;
            color: #d8d9da;
        }

        .panel-count {
            font-size: 12px;
            color: #8e9ba7;
            background: #111217;
            padding: 2px 8px;
            border-radius: 10px;
            border: 1px solid #2c3235;
        }

        /* ---- Filtres ---- */
        .filtres {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            padding: 12px 16px;
            background: #1c2127;
            border-bottom: 1px solid #2c3235;
        }

        .filtres a {
            display: inline-block;
            padding: 5px 14px;
            background: #111217;
            color: #8e9ba7;
            border: 1px solid #2c3235;
            border-radius: 4px;
            text-decoration: none;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.15s;
        }

        .filtres a:hover {
            background: #1f2937;
            color: #d8d9da;
            border-color: #44444c;
        }

        .filtres a.actif {
            background: #ff7800;
            color: #fff;
            border-color: #ff7800;
        }

        /* ---- Tableau ---- */
        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        thead tr {
            background: #1c2127;
        }

        th {
            padding: 10px 12px;
            text-align: left;
            font-size: 11px;
            font-weight: 600;
            color: #8e9ba7;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #2c3235;
            white-space: nowrap;
            cursor: pointer;
            user-select: none;
        }

        th:hover {
            color: #d8d9da;
            background: #222630;
        }

        th .sort-icon {
            display: inline-block;
            margin-left: 5px;
            font-size: 10px;
            color: #454c53;
            vertical-align: middle;
            transition: color 0.15s;
        }

        th.sort-asc .sort-icon,
        th.sort-desc .sort-icon {
            color: #ff7800;
        }

        td {
            padding: 9px 12px;
            border-bottom: 1px solid #1a1d21;
            color: #c7d0d9;
            vertical-align: middle;
        }

        tbody tr:hover td {
            background: #1f2937;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        /* ---- Colonne ID ---- */
        .col-id {
            color: #6c737a;
            font-size: 12px;
            font-variant-numeric: tabular-nums;
        }

        /* ---- Adresse IP ---- */
        .col-ip {
            font-family: 'Courier New', monospace;
            color: #6e9fff;
            font-size: 12px;
        }

        /* ---- Badge type ---- */
        .badge-type {
            background: #1f2937;
            color: #aac8ff;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 500;
            border: 1px solid #2c3a4a;
            white-space: nowrap;
        }

        /* ---- Badge état ---- */
        .etat-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 10px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.3px;
            white-space: nowrap;
        }

        .etat-badge::before {
            content: '';
            display: inline-block;
            width: 7px;
            height: 7px;
            border-radius: 50%;
        }

        .etat-true {
            background: #1a3a1a;
            color: #73bf69;
            border: 1px solid #2d5a2d;
        }

        .etat-true::before {
            background: #73bf69;
            box-shadow: 0 0 6px #73bf69;
        }

        .etat-false {
            background: #3a1a1a;
            color: #f2495c;
            border: 1px solid #5a2d2d;
        }

        .etat-false::before {
            background: #f2495c;
        }

        /* ---- Vide ---- */
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #6c737a;
            font-size: 13px;
        }

        /* ---- Bouton ajouter ---- */
        .btn-add {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 14px;
            background: #ff7800;
            color: #fff;
            border: none;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.15s;
        }
        .btn-add:hover { background: #e06900; }

        /* ---- Modal ---- */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.6);
            z-index: 200;
            align-items: center;
            justify-content: center;
        }
        .modal-overlay.open { display: flex; }

        .modal {
            background: #181b1f;
            border: 1px solid #2c3235;
            border-radius: 6px;
            width: 560px;
            max-width: 95vw;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            padding: 14px 20px;
            border-bottom: 1px solid #2c3235;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-header span {
            font-size: 14px;
            font-weight: 600;
            color: #d8d9da;
        }

        .modal-close {
            background: none;
            border: none;
            color: #6c737a;
            font-size: 18px;
            cursor: pointer;
            line-height: 1;
        }
        .modal-close:hover { color: #d8d9da; }

        .modal-body {
            padding: 20px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .modal-body .full { grid-column: 1 / -1; }

        .form-group label {
            display: block;
            font-size: 11px;
            font-weight: 600;
            color: #8e9ba7;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            background: #111217;
            border: 1px solid #2c3235;
            border-radius: 4px;
            color: #d8d9da;
            font-size: 13px;
            padding: 7px 10px;
            outline: none;
            transition: border-color 0.15s;
        }

        .form-group input:focus,
        .form-group select:focus { border-color: #ff7800; }

        .modal-footer {
            padding: 14px 20px;
            border-top: 1px solid #2c3235;
            display: flex;
            justify-content: flex-end;
            gap: 8px;
        }

        .btn-cancel {
            padding: 7px 16px;
            background: #111217;
            border: 1px solid #2c3235;
            border-radius: 4px;
            color: #8e9ba7;
            font-size: 13px;
            cursor: pointer;
        }
        .btn-cancel:hover { background: #1f2937; color: #d8d9da; }

        .btn-submit {
            padding: 7px 16px;
            background: #ff7800;
            border: none;
            border-radius: 4px;
            color: #fff;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-submit:hover { background: #e06900; }
        .btn-submit:disabled { opacity: 0.5; cursor: not-allowed; }

        /* ---- Bouton modifier ---- */
        .btn-edit {
            background: none;
            border: 1px solid #2c4a6a;
            border-radius: 3px;
            color: #6e9fff;
            font-size: 11px;
            padding: 3px 8px;
            cursor: pointer;
            transition: background 0.15s;
            margin-right: 4px;
        }
        .btn-edit:hover { background: #1a2a3a; }

        /* ---- Bouton supprimer ---- */
        .btn-delete {
            background: none;
            border: 1px solid #5a2d2d;
            border-radius: 3px;
            color: #f2495c;
            font-size: 11px;
            padding: 3px 8px;
            cursor: pointer;
            transition: background 0.15s;
        }
        .btn-delete:hover { background: #3a1a1a; }

        /* ---- Modal confirmation ---- */
        .modal-confirm {
            background: #181b1f;
            border: 1px solid #2c3235;
            border-radius: 6px;
            width: 380px;
            max-width: 95vw;
            padding: 24px;
            text-align: center;
        }
        .modal-confirm p {
            color: #d8d9da;
            font-size: 14px;
            margin-bottom: 6px;
        }
        .modal-confirm .confirm-name {
            color: #f2495c;
            font-weight: 600;
        }
        .modal-confirm .confirm-buttons {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
        }
        .btn-danger {
            padding: 7px 20px;
            background: #c0392b;
            border: none;
            border-radius: 4px;
            color: #fff;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-danger:hover { background: #a93226; }

        /* ---- Footer ---- */
        .footer {
            text-align: center;
            padding: 16px;
            color: #454c53;
            font-size: 11px;
            margin-top: 8px;
        }
    </style>
</head>
<body>

<!-- ---- Topbar ---- -->
<div class="topbar">
    <a class="topbar-logo" href="?">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>
        </svg>
        NetDash
    </a>
    <span class="topbar-title">Plan d'adressage réseau</span>
    <div class="topbar-time" id="clock"></div>
</div>

<!-- ---- Contenu ---- -->
<div class="main">

    <!-- Stat cards -->
    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-card-label">Total équipements</div>
            <div class="stat-card-value total" id="stat-total"><?= (int)$stats['total'] ?></div>
            <div class="stat-card-sub">Tous types confondus</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-label">En ligne</div>
            <div class="stat-card-value online" id="stat-online"><?= (int)$stats['en_ligne'] ?></div>
            <div class="stat-card-sub" id="stat-pct-online"><?= $stats['total'] > 0 ? round($stats['en_ligne'] / $stats['total'] * 100) : 0 ?>% du parc</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-label">Hors ligne</div>
            <div class="stat-card-value offline" id="stat-offline"><?= (int)$stats['hors_ligne'] ?></div>
            <div class="stat-card-sub" id="stat-pct-offline"><?= $stats['total'] > 0 ? round($stats['hors_ligne'] / $stats['total'] * 100) : 0 ?>% du parc</div>
        </div>
    </div>

    <!-- Panel principal -->
    <div class="panel">
        <div class="panel-header">
            <span class="panel-title">Équipements réseau</span>
            <span class="panel-count" id="result-count"><?= $result->num_rows ?> résultat(s)</span>
            <span id="refresh-indicator" style="font-size:11px;color:#6c737a;margin-left:8px;transition:color 0.4s;">Actualisation auto toutes les 15s</span>
            <button class="btn-add" onclick="openModal()" style="margin-left:auto;">+ Ajouter</button>
        </div>

        <!-- Filtres -->
        <div class="filtres">
            <a href="?type=Tous" class="<?= ($filtre === 'Tous') ? 'actif' : '' ?>">Tous</a>
            <?php while ($row_type = $types_result->fetch_assoc()): ?>
                <a href="?type=<?= urlencode($row_type['type_peripherique']) ?>"
                   class="<?= ($filtre === $row_type['type_peripherique']) ? 'actif' : '' ?>">
                    <?= htmlspecialchars($row_type['type_peripherique']) ?>
                </a>
            <?php endwhile; ?>
        </div>

        <!-- Tableau -->
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th data-col="0">#<span class="sort-icon">⇅</span></th>
                        <th data-col="1">Équipement<span class="sort-icon">⇅</span></th>
                        <th data-col="2">Marque / Modèle<span class="sort-icon">⇅</span></th>
                        <th data-col="3">Emplacement<span class="sort-icon">⇅</span></th>
                        <th data-col="4">Adresse IP<span class="sort-icon">⇅</span></th>
                        <th data-col="5">Masque<span class="sort-icon">⇅</span></th>
                        <th data-col="6">Passerelle<span class="sort-icon">⇅</span></th>
                        <th data-col="7">Type<span class="sort-icon">⇅</span></th>
                        <th data-col="8">État<span class="sort-icon">⇅</span></th>
                        <th data-col="9">Spécification<span class="sort-icon">⇅</span></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows === 0): ?>
                        <tr>
                            <td colspan="10" class="empty-state">Aucun équipement trouvé.</td>
                        </tr>
                    <?php else: ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td class="col-id"><?= $row['id'] ?></td>
                                <td><?= htmlspecialchars($row['nom_equipement']) ?></td>
                                <td><?= htmlspecialchars($row['marque_modele']) ?></td>
                                <td><?= htmlspecialchars($row['emplacement_physique']) ?></td>
                                <td class="col-ip"><?= htmlspecialchars($row['adresse_ip']) ?></td>
                                <td class="col-ip"><?= htmlspecialchars($row['masque_sous_reseau']) ?></td>
                                <td class="col-ip"><?= htmlspecialchars($row['passerelle']) ?></td>
                                <td><span class="badge-type"><?= htmlspecialchars($row['type_peripherique']) ?></span></td>
                                <td>
                                    <?php if (strtoupper($row['etat']) === 'TRUE'): ?>
                                        <span class="etat-badge etat-true">EN LIGNE</span>
                                    <?php else: ?>
                                        <span class="etat-badge etat-false">HORS LIGNE</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($row['specification']) ?></td>
                                <td style="white-space:nowrap;">
                                    <button class="btn-edit" onclick="openEdit(<?= htmlspecialchars(json_encode($row), ENT_QUOTES) ?>)">Modifier</button>
                                    <button class="btn-delete" onclick="confirmDelete(<?= $row['id'] ?>, '<?= addslashes(htmlspecialchars($row['nom_equipement'])) ?>')">Supprimer</button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="footer">NetDash &mdash; <?= date('Y') ?></div>
</div>

<!-- ---- Modal Confirmation Suppression ---- -->
<div class="modal-overlay" id="modal-confirm-overlay">
    <div class="modal-confirm">
        <p>Supprimer l'équipement :</p>
        <p class="confirm-name" id="confirm-name"></p>
        <p style="color:#6c737a;font-size:12px;margin-top:8px;">Cette action est irréversible.</p>
        <div class="confirm-buttons">
            <button class="btn-cancel" onclick="closeConfirm()">Annuler</button>
            <button class="btn-danger" id="btn-confirm-delete" onclick="doDelete()">Supprimer</button>
        </div>
    </div>
</div>

<!-- ---- Modal Ajout ---- -->
<div class="modal-overlay" id="modal-overlay">
    <div class="modal">
        <div class="modal-header">
            <span id="modal-title">Ajouter un équipement</span>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label>Nom équipement *</label>
                <input type="text" id="f-nom" placeholder="ex: Switch-Bureau-01">
            </div>
            <div class="form-group">
                <label>Marque / Modèle</label>
                <input type="text" id="f-marque" placeholder="ex: Cisco SG350">
            </div>
            <div class="form-group">
                <label>Emplacement physique</label>
                <input type="text" id="f-emplacement" placeholder="ex: Salle serveur">
            </div>
            <div class="form-group">
                <label>Type</label>
                <select id="f-type">
                    <option value="">-- Choisir --</option>
                    <option value="Caméra">Caméra</option>
                    <option value="NVR">NVR</option>
                    <option value="Ordinateur">Ordinateur</option>
                    <option value="Routeur">Routeur</option>
                    <option value="Serveur">Serveur</option>
                    <option value="Switch">Switch</option>
                </select>
            </div>
            <div class="form-group">
                <label>Adresse IP</label>
                <input type="text" id="f-ip" placeholder="ex: 192.168.1.10">
            </div>
            <div class="form-group">
                <label>Masque sous-réseau</label>
                <input type="text" id="f-masque" placeholder="ex: 255.255.255.0">
            </div>
            <div class="form-group">
                <label>Passerelle</label>
                <input type="text" id="f-passerelle" placeholder="ex: 192.168.1.1">
            </div>
            <div class="form-group">
                <label>État</label>
                <select id="f-etat">
                    <option value="TRUE">En ligne</option>
                    <option value="FALSE">Hors ligne</option>
                </select>
            </div>
            <div class="form-group full">
                <label>Spécification</label>
                <input type="text" id="f-spec" placeholder="Notes, détails...">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-cancel" onclick="closeModal()">Annuler</button>
            <button class="btn-submit" id="btn-submit" onclick="submitForm()">Enregistrer</button>
        </div>
    </div>
</div>

<script>
    // ---- Tri de colonnes ----
    (function () {
        const table = document.querySelector('table');
        if (!table) return;

        let currentCol = null;
        let ascending = true;

        // Compare deux cellules : numérique si possible, sinon textuel
        function cellValue(row, col) {
            return row.cells[col] ? row.cells[col].innerText.trim() : '';
        }

        function compareValues(a, b, asc) {
            const numA = parseFloat(a.replace(/[^0-9.\-]/g, ''));
            const numB = parseFloat(b.replace(/[^0-9.\-]/g, ''));
            let cmp;
            if (!isNaN(numA) && !isNaN(numB)) {
                cmp = numA - numB;
            } else {
                cmp = a.localeCompare(b, 'fr', { sensitivity: 'base' });
            }
            return asc ? cmp : -cmp;
        }

        table.querySelectorAll('thead th[data-col]').forEach(function (th) {
            th.addEventListener('click', function () {
                const col = parseInt(th.dataset.col);
                if (currentCol === col) {
                    ascending = !ascending;
                } else {
                    currentCol = col;
                    ascending = true;
                }

                // Mise à jour icônes
                table.querySelectorAll('thead th').forEach(function (h) {
                    h.classList.remove('sort-asc', 'sort-desc');
                    const icon = h.querySelector('.sort-icon');
                    if (icon) icon.textContent = '⇅';
                });
                th.classList.add(ascending ? 'sort-asc' : 'sort-desc');
                const icon = th.querySelector('.sort-icon');
                if (icon) icon.textContent = ascending ? '↑' : '↓';

                // Tri des lignes
                const tbody = table.querySelector('tbody');
                const rows = Array.from(tbody.querySelectorAll('tr'));

                // Ne pas trier si ligne "vide"
                if (rows.length === 1 && rows[0].cells.length === 1) return;

                rows.sort(function (a, b) {
                    return compareValues(cellValue(a, col), cellValue(b, col), ascending);
                });

                rows.forEach(function (row) { tbody.appendChild(row); });
            });
        });
    })();

    // ---- Polling des données ----
    const POLL_INTERVAL = 15000; // ms
    let currentFiltre = new URLSearchParams(window.location.search).get('type') || 'Tous';

    function renderRow(row) {
        const enLigne = row.etat && row.etat.toUpperCase() === 'TRUE';
        const etatHtml = enLigne
            ? '<span class="etat-badge etat-true">EN LIGNE</span>'
            : '<span class="etat-badge etat-false">HORS LIGNE</span>';
        return `<tr>
            <td class="col-id">${escHtml(row.id)}</td>
            <td>${escHtml(row.nom_equipement)}</td>
            <td>${escHtml(row.marque_modele)}</td>
            <td>${escHtml(row.emplacement_physique)}</td>
            <td class="col-ip">${escHtml(row.adresse_ip)}</td>
            <td class="col-ip">${escHtml(row.masque_sous_reseau)}</td>
            <td class="col-ip">${escHtml(row.passerelle)}</td>
            <td><span class="badge-type">${escHtml(row.type_peripherique)}</span></td>
            <td>${etatHtml}</td>
            <td>${escHtml(row.specification)}</td>
            <td style="white-space:nowrap;">
                <button class="btn-edit" data-row="${JSON.stringify(row).replace(/"/g, '&quot;')}" onclick="openEdit(JSON.parse(this.dataset.row))">Modifier</button>
                <button class="btn-delete" onclick="confirmDelete(${escHtml(row.id)}, '${escHtml(row.nom_equipement).replace(/'/g, "\\'")}')">Supprimer</button>
            </td>
        </tr>`;
    }

    function escHtml(str) {
        if (str == null) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function refreshData() {
        const url = 'api.php?type=' + encodeURIComponent(currentFiltre);
        fetch(url)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                // Mise à jour stats
                const total   = parseInt(data.stats.total)    || 0;
                const online  = parseInt(data.stats.en_ligne) || 0;
                const offline = parseInt(data.stats.hors_ligne) || 0;
                document.getElementById('stat-total').textContent   = total;
                document.getElementById('stat-online').textContent  = online;
                document.getElementById('stat-offline').textContent = offline;
                document.getElementById('stat-pct-online').textContent  = total > 0 ? Math.round(online  / total * 100) + '% du parc' : '0% du parc';
                document.getElementById('stat-pct-offline').textContent = total > 0 ? Math.round(offline / total * 100) + '% du parc' : '0% du parc';

                // Mise à jour tbody
                const tbody = document.querySelector('table tbody');
                if (data.rows.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="11" class="empty-state">Aucun équipement trouvé.</td></tr>';
                } else {
                    tbody.innerHTML = data.rows.map(renderRow).join('');
                }

                // Mise à jour compteur
                document.getElementById('result-count').textContent = data.rows.length + ' résultat(s)';

                // Indicateur de fraîcheur
                const indicator = document.getElementById('refresh-indicator');
                indicator.style.color = '#73bf69';
                indicator.textContent = 'Actualisé à ' + new Date().toLocaleTimeString('fr-FR');
                setTimeout(function () { indicator.style.color = ''; }, 1500);
            })
            .catch(function () {
                const indicator = document.getElementById('refresh-indicator');
                indicator.style.color = '#f2495c';
                indicator.textContent = 'Erreur de connexion';
            });
    }

    // Premier chargement immédiat puis polling
    setTimeout(refreshData, POLL_INTERVAL);
    setInterval(refreshData, POLL_INTERVAL);

    // ---- Suppression ----
    let deleteId = null;

    function confirmDelete(id, nom) {
        deleteId = id;
        document.getElementById('confirm-name').textContent = nom;
        document.getElementById('modal-confirm-overlay').classList.add('open');
    }

    function closeConfirm() {
        deleteId = null;
        document.getElementById('modal-confirm-overlay').classList.remove('open');
    }

    function doDelete() {
        if (!deleteId) return;
        const btn = document.getElementById('btn-confirm-delete');
        btn.disabled = true;
        fetch('api.php?id=' + deleteId, { method: 'DELETE' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    closeConfirm();
                    refreshData();
                } else {
                    alert('Erreur : ' + (data.error || 'inconnue'));
                }
            })
            .catch(function() { alert('Erreur de connexion.'); })
            .finally(function() { btn.disabled = false; });
    }

    document.getElementById('modal-confirm-overlay').addEventListener('click', function(e) {
        if (e.target === this) closeConfirm();
    });

    // ---- Modal Ajout / Modification ----
    let editId = null;

    function openModal() {
        editId = null;
        document.getElementById('modal-title').textContent = 'Ajouter un équipement';
        document.getElementById('modal-overlay').classList.add('open');
    }

    function openEdit(row) {
        editId = row.id;
        document.getElementById('modal-title').textContent = 'Modifier un équipement';
        document.getElementById('f-nom').value        = row.nom_equipement || '';
        document.getElementById('f-marque').value     = row.marque_modele || '';
        document.getElementById('f-emplacement').value= row.emplacement_physique || '';
        document.getElementById('f-type').value       = row.type_peripherique || '';
        document.getElementById('f-ip').value         = row.adresse_ip || '';
        document.getElementById('f-masque').value     = row.masque_sous_reseau || '';
        document.getElementById('f-passerelle').value = row.passerelle || '';
        document.getElementById('f-etat').value       = (row.etat && row.etat.toUpperCase() === 'TRUE') ? 'TRUE' : 'FALSE';
        document.getElementById('f-spec').value       = row.specification || '';
        document.getElementById('modal-overlay').classList.add('open');
    }

    function closeModal() {
        document.getElementById('modal-overlay').classList.remove('open');
        editId = null;
        ['f-nom','f-marque','f-emplacement','f-ip','f-masque','f-passerelle','f-spec'].forEach(function(id) {
            document.getElementById(id).value = '';
        });
        document.getElementById('f-type').value = '';
        document.getElementById('f-etat').value = 'TRUE';
    }

    document.getElementById('modal-overlay').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
    });

    function submitForm() {
        const nom = document.getElementById('f-nom').value.trim();
        if (!nom) { alert('Le nom de l\'équipement est obligatoire.'); return; }

        const btn = document.getElementById('btn-submit');
        btn.disabled = true;
        btn.textContent = 'Envoi...';

        const payload = {
            nom_equipement:      nom,
            marque_modele:       document.getElementById('f-marque').value.trim(),
            emplacement_physique:document.getElementById('f-emplacement').value.trim(),
            type_peripherique:   document.getElementById('f-type').value.trim(),
            adresse_ip:          document.getElementById('f-ip').value.trim(),
            masque_sous_reseau:  document.getElementById('f-masque').value.trim(),
            passerelle:          document.getElementById('f-passerelle').value.trim(),
            etat:                document.getElementById('f-etat').value,
            specification:       document.getElementById('f-spec').value.trim()
        };

        const method = editId ? 'PUT' : 'POST';
        const url    = editId ? 'api.php?id=' + editId : 'api.php';

        fetch(url, {
            method: method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                closeModal();
                refreshData();
            } else {
                alert('Erreur : ' + (data.error || 'inconnue'));
            }
        })
        .catch(function() { alert('Erreur de connexion.'); })
        .finally(function() {
            btn.disabled = false;
            btn.textContent = 'Enregistrer';
        });
    }

    // ---- Horloge ----
    function updateClock() {
        const now = new Date();
        const d = String(now.getDate()).padStart(2, '0');
        const m = String(now.getMonth() + 1).padStart(2, '0');
        const y = now.getFullYear();
        const h = String(now.getHours()).padStart(2, '0');
        const min = String(now.getMinutes()).padStart(2, '0');
        const s = String(now.getSeconds()).padStart(2, '0');
        document.getElementById('clock').textContent = `${d}/${m}/${y} ${h}:${min}:${s}`;
    }
    updateClock();
    setInterval(updateClock, 1000);
</script>
</body>
</html>
<?php $conn->close(); ?>
