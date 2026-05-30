<?php
// Configuración de la aplicación
$page_title = "Formato de Compatibilidad - TecNM";

// Incluir conexión a la base de datos
require_once 'conexion.php';

// Datos de ejemplo (en producción vendrían de BD)
$datos_rfc = "RORV740111AX7";
$institucion1 = "TECNOLÓGICO NACIONAL DE MÉXICO";
$institucion2 = "CETMAR NO. 11";
$meses = ['01'=>'Enero','02'=>'Febrero','03'=>'Marzo','04'=>'Abril','05'=>'Mayo','06'=>'Junio','07'=>'Julio','08'=>'Agosto','09'=>'Septiembre','10'=>'Octubre','11'=>'Noviembre','12'=>'Diciembre'];

// Obtener lista de plazas desde la base de datos
$plazas = [];
$sql_plazas = "SELECT Clave_Presupuestal, CD_Trabajo, Categoria, Des_Categoria, Salario FROM plazas ORDER BY Categoria, Des_Categoria";
$result_plazas = $conn->query($sql_plazas);
if ($result_plazas && $result_plazas->num_rows > 0) {
    while ($row = $result_plazas->fetch_assoc()) {
        $plazas[] = $row;
    }
}

// Obtener lista de trabajadores para sugerencias
$trabajadores = [];
$sql_trabajadores = "SELECT ID_Trabajador, apPaterno, apMaterno, nombre, RFC FROM trabajadores";
$result_trabajadores = $conn->query($sql_trabajadores);
if ($result_trabajadores && $result_trabajadores->num_rows > 0) {
    while ($row = $result_trabajadores->fetch_assoc()) {
        $trabajadores[] = $row;
    }
}

// Procesamiento del formulario
$submitted = false;
$errors = [];
$mensaje_exito = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validaciones básicas
    if (empty($_POST['apellido_paterno'])) $errors[] = "Apellido paterno es requerido.";
    if (empty($_POST['nombre'])) $errors[] = "Nombre es requerido.";
    if (empty($_POST['rfc'])) $errors[] = "RFC es requerido.";
    if (empty($_POST['puesto_actual'])) $errors[] = "El puesto actual es requerido.";

    if (empty($errors)) {
        // Verificar si el trabajador ya existe por RFC
        $rfc = strtoupper(trim($_POST['rfc']));
        $sql_check = "SELECT ID_Trabajador FROM trabajadores WHERE RFC = ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("s", $rfc);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();

        if ($result_check->num_rows > 0) {
            // Trabajador existe, obtener su ID
            $row = $result_check->fetch_assoc();
            $id_trabajador = $row['ID_Trabajador'];
        } else {
            // Insertar nuevo trabajador
            $sql_insert_trabajador = "INSERT INTO trabajadores (apPaterno, apMaterno, nombre, RFC) VALUES (?, ?, ?, ?)";
            $stmt_insert = $conn->prepare($sql_insert_trabajador);
            $ap_paterno = strtoupper(trim($_POST['apellido_paterno']));
            $ap_materno = strtoupper(trim($_POST['apellido_materno'] ?? ''));
            $nombre = strtoupper(trim($_POST['nombre']));
            $stmt_insert->bind_param("ssss", $ap_paterno, $ap_materno, $nombre, $rfc);

            if ($stmt_insert->execute()) {
                $id_trabajador = $conn->insert_id;
            } else {
                $errors[] = "Error al guardar los datos del trabajador: " . $conn->error;
            }
            $stmt_insert->close();
        }
        $stmt_check->close();

        // Si no hay errores, guardar la compatibilidad
        if (empty($errors)) {
            $tipo_movimiento  = ($_POST['resolucion'] ?? 'A') == 'A' ? 1 : 0;
            $temporalidad_inc = $_POST['auth_desde'] ?? date('Y-m-d');
            $temporalidad_fin = $_POST['auth_hasta'] ?? date('Y-m-d', strtotime('+1 year'));
            $plaza_activa     = $_POST['codigo_presupuestal2'] ?? '';
            $ciudad           = $_POST['lugar'] ?? 'ENSENADA';
            $fecha_creacion   = date('Y-m-d');
            // CORRECCIÓN FK: buscar Clave_Presupuestal real en plazas usando la Categoria del formulario
            $categoria_sel = $_POST['codigo_presupuestal2'] ?? '';
            $clave_presupuestal = null;
            if (!empty($categoria_sel)) {
                $sql_busca_plaza = "SELECT Clave_Presupuestal FROM plazas WHERE Categoria = ? LIMIT 1";
                $stmt_busca = $conn->prepare($sql_busca_plaza);
                $stmt_busca->bind_param("s", $categoria_sel);
                $stmt_busca->execute();
                $res_plaza = $stmt_busca->get_result();
                if ($row_plaza = $res_plaza->fetch_assoc()) {
                    $clave_presupuestal = $row_plaza['Clave_Presupuestal'];
                }
                $stmt_busca->close();
            }
            // OPCION A: solo columnas que existen en la tabla compatibilidad
            $ubicacion        = $_POST['ubicacion1'] ?? '';
            $horario          = '';
            $tiempo_traslado  = '';

            // OPCION A: INSERT ajustado a columnas reales de la tabla compatibilidad
            $sql = "INSERT INTO compatibilidad (
                Tipo_de_Movimiento, Temporalidad_INC, Temporalidad_FIN,
                Plaza_Activa, Ciudad, Fecha_de_Creacion,
                Ubicacion, Horario, Tiempo_de_Traslado,
                ID_Trabajador, Clave_Presupuestal
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param(
                "isssssssssi",
                $tipo_movimiento, $temporalidad_inc, $temporalidad_fin,
                $plaza_activa, $ciudad, $fecha_creacion,
                $ubicacion, $horario, $tiempo_traslado,
                $id_trabajador, $clave_presupuestal
            );

            if ($stmt->execute()) {
                $id_compatibilidad = $conn->insert_id; // OPCION A: guardar ID para usarlo en compatibilidad_puestos

                // OPCION A: guardar puesto de institución 1 en compatibilidad_puestos
                $fecha_alta1 = null;
                if (!empty($_POST['alta_dia1']) && !empty($_POST['alta_mes1']) && !empty($_POST['alta_ano1'])) {
                    $fecha_alta1 = $_POST['alta_ano1'] . '-' . $_POST['alta_mes1'] . '-' . str_pad($_POST['alta_dia1'], 2, '0', STR_PAD_LEFT);
                }
                $fecha_fin1 = null;
                if (!empty($_POST['fin_dia1']) && !empty($_POST['fin_mes1']) && !empty($_POST['fin_ano1'])) {
                    $fecha_fin1 = $_POST['fin_ano1'] . '-' . $_POST['fin_mes1'] . '-' . str_pad($_POST['fin_dia1'], 2, '0', STR_PAD_LEFT);
                }
                $sql_p1 = "INSERT INTO compatibilidad_puestos (ID_Compatibilidad, Institucion_Num, Puesto, Codigo_Presupuestal, Unidad_Adscripcion, Tipo_Nombramiento, Fecha_Alta, Fecha_Fin, Remuneracion, Ubicacion) VALUES (?, 1, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt_p1 = $conn->prepare($sql_p1);
                $p1_puesto  = $_POST['puesto_actual'] ?? '';
                $p1_codigo  = $_POST['codigo_presupuestal1'] ?? '';
                $p1_unidad  = $_POST['unidad_adscripcion1'] ?? '';
                $p1_tipo    = $_POST['tipo_nombramiento1'] ?? '';
                $p1_rem     = $_POST['remuneracion1'] ?? 0;
                $p1_ubic    = $_POST['ubicacion1'] ?? '';
                $stmt_p1->bind_param("issssssdss", $id_compatibilidad, $p1_puesto, $p1_codigo, $p1_unidad, $p1_tipo, $fecha_alta1, $fecha_fin1, $p1_rem, $p1_ubic);
                $stmt_p1->execute();
                $stmt_p1->close();

                // OPCION A: guardar puesto de institución 2 en compatibilidad_puestos
                $fecha_alta2 = null;
                if (!empty($_POST['alta_dia2']) && !empty($_POST['alta_mes2']) && !empty($_POST['alta_ano2'])) {
                    $fecha_alta2 = $_POST['alta_ano2'] . '-' . $_POST['alta_mes2'] . '-' . str_pad($_POST['alta_dia2'], 2, '0', STR_PAD_LEFT);
                }
                $fecha_fin2 = null;
                if (!empty($_POST['fin_dia2']) && !empty($_POST['fin_mes2']) && !empty($_POST['fin_ano2'])) {
                    $fecha_fin2 = $_POST['fin_ano2'] . '-' . $_POST['fin_mes2'] . '-' . str_pad($_POST['fin_dia2'], 2, '0', STR_PAD_LEFT);
                }
                $sql_p2 = "INSERT INTO compatibilidad_puestos (ID_Compatibilidad, Institucion_Num, Puesto, Codigo_Presupuestal, Unidad_Adscripcion, Tipo_Nombramiento, Fecha_Alta, Fecha_Fin, Remuneracion, Ubicacion) VALUES (?, 2, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt_p2 = $conn->prepare($sql_p2);
                $p2_puesto  = $_POST['puesto_nuevo'] ?? '';
                $p2_codigo  = $_POST['codigo_presupuestal2'] ?? '';
                $p2_unidad  = $_POST['unidad_adscripcion2'] ?? '';
                $p2_tipo    = $_POST['tipo_nombramiento2'] ?? '';
                $p2_rem     = $_POST['remuneracion2'] ?? 0;
                $p2_ubic    = $_POST['ubicacion2'] ?? '';
                $stmt_p2->bind_param("issssssdss", $id_compatibilidad, $p2_puesto, $p2_codigo, $p2_unidad, $p2_tipo, $fecha_alta2, $fecha_fin2, $p2_rem, $p2_ubic);
                $stmt_p2->execute();
                $stmt_p2->close();

                $submitted = true;
                $mensaje_exito = "Solicitud registrada exitosamente con ID: " . $id_compatibilidad;
            } else {
                $errors[] = "Error al guardar: " . $stmt->error;
            }

            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@300;400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f5f3ee;
            --surface: #ffffff;
            --surface-alt: #f0ede5;
            --border: #c8c2b4;
            --border-strong: #8a8070;
            --text: #1a1714;
            --text-muted: #6b6558;
            --accent: #1a3a5c;
            --accent-light: #e8eef5;
            --accent2: #8b4513;
            --success: #2d6a4f;
            --success-bg: #e8f5ef;
            --error: #c0392b;
            --error-bg: #fdf0ee;
            --warning: #7d5a00;
            --warning-bg: #fef9e7;
            --seal: #1a3a5c;
            --font-main: 'IBM Plex Sans', sans-serif;
            --font-mono: 'IBM Plex Mono', monospace;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: var(--font-main);
            background: var(--bg);
            color: var(--text);
            font-size: 14px;
            line-height: 1.5;
        }

        .page-header {
            background: var(--accent);
            color: white;
            padding: 0;
            border-bottom: 4px solid var(--accent2);
        }
        .header-inner {
            max-width: 1100px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 14px 24px;
        }
        .header-titles { flex: 1; }
        .header-titles h1 {
            font-size: 15px;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }
        .header-titles .sub {
            font-size: 11px;
            font-weight: 300;
            opacity: 0.75;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            margin-top: 2px;
        }
        .header-meta {
            text-align: right;
            font-family: var(--font-mono);
            font-size: 10px;
            opacity: 0.7;
            line-height: 1.8;
        }

        .section-nav {
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            position: sticky; top: 0; z-index: 100;
        }
        .nav-inner {
            max-width: 1100px; margin: 0 auto;
            display: flex; gap: 0;
        }
        .nav-tab {
            padding: 10px 20px;
            font-size: 12px;
            font-weight: 500;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: var(--text-muted);
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.2s;
            text-decoration: none;
            display: block;
        }
        .nav-tab:hover { color: var(--accent); }
        .nav-tab.active {
            color: var(--accent);
            border-bottom-color: var(--accent);
        }

        .main-wrap {
            max-width: 1100px;
            margin: 0 auto;
            padding: 28px 24px 60px;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 13px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        .alert-error { background: var(--error-bg); border-left: 4px solid var(--error); color: var(--error); }
        .alert-success { background: var(--success-bg); border-left: 4px solid var(--success); color: var(--success); }
        .alert ul { padding-left: 16px; margin-top: 4px; }

        .formato-doc {
            background: var(--surface);
            border: 1px solid var(--border);
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
        }

        .doc-section { border-bottom: 1px solid var(--border); }
        .doc-section:last-child { border-bottom: none; }

        .section-header {
            background: var(--accent);
            color: white;
            padding: 8px 16px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .section-header.alt {
            background: var(--surface-alt);
            color: var(--accent);
            border-bottom: 1px solid var(--border);
        }

        .section-body { padding: 16px; }

        .field-row {
            display: grid;
            gap: 12px;
            margin-bottom: 12px;
        }
        .field-row:last-child { margin-bottom: 0; }
        .col-1 { grid-template-columns: 1fr; }
        .col-2 { grid-template-columns: 1fr 1fr; }
        .col-3 { grid-template-columns: 1fr 1fr 1fr; }
        .col-4 { grid-template-columns: 1fr 1fr 1fr 1fr; }
        .col-2-1 { grid-template-columns: 2fr 1fr; }
        .col-3-1 { grid-template-columns: 3fr 1fr; }
        .col-rfc { grid-template-columns: 1fr 1fr 2fr 160px; }

        .field { display: flex; flex-direction: column; gap: 3px; }
        .field label {
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: var(--text-muted);
        }
        .field label .req { color: var(--error); }

        .field input[type="text"],
        .field input[type="date"],
        .field input[type="number"],
        .field select,
        .field textarea {
            border: 1px solid var(--border);
            background: var(--bg);
            padding: 7px 10px;
            font-family: var(--font-main);
            font-size: 13px;
            color: var(--text);
            border-radius: 3px;
            transition: border-color 0.15s, box-shadow 0.15s;
            width: 100%;
        }
        .field input:focus,
        .field select:focus,
        .field textarea:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-light);
        }
        .field textarea { resize: vertical; min-height: 64px; }
        .field input[readonly] {
            background: #e8e4dc;
            color: var(--text-muted);
            cursor: default;
        }

        .radio-label {
            display: flex; align-items: center; gap: 5px;
            font-size: 12px; cursor: pointer;
        }
        .radio-label input { accent-color: var(--accent); }

        .firma-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }
        .firma-box {
            border: 1px solid var(--border);
            padding: 16px;
            text-align: center;
        }
        .firma-inst {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: var(--accent);
            margin-bottom: 8px;
        }
        .firma-line {
            border-top: 1px solid var(--border-strong);
            margin: 40px 16px 8px;
        }
        .firma-rol {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
        }
        .firma-nombre-input {
            border: none;
            border-bottom: 1px solid var(--border);
            background: transparent;
            text-align: center;
            font-size: 13px;
            width: 100%;
            padding: 4px;
            margin-top: 4px;
        }
        .firma-nombre-input:focus { outline: none; border-bottom-color: var(--accent); }

        .nota-legal {
            background: var(--warning-bg);
            border: 1px solid #e6c84a;
            padding: 12px 16px;
            font-size: 12px;
            color: var(--warning);
            line-height: 1.6;
        }

        .auth-options { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .auth-option {
            border: 1px solid var(--border);
            padding: 14px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .auth-option:hover { border-color: var(--accent); background: var(--accent-light); }
        .auth-option.selected { border-color: var(--accent); background: var(--accent-light); }
        .auth-option label { display: flex; gap: 10px; align-items: flex-start; cursor: pointer; }
        .auth-option input[type=radio] { accent-color: var(--accent); margin-top: 2px; }
        .auth-option .opt-title { font-weight: 600; font-size: 13px; color: var(--accent); }
        .auth-option .opt-desc { font-size: 12px; color: var(--text-muted); margin-top: 4px; line-height: 1.5; }

        .actions-bar {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            padding: 16px;
            background: var(--surface-alt);
            border-top: 1px solid var(--border);
        }
        .btn {
            padding: 10px 24px;
            border: none;
            cursor: pointer;
            font-family: var(--font-main);
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            border-radius: 3px;
            transition: all 0.15s;
        }
        .btn-primary { background: var(--accent); color: white; }
        .btn-primary:hover { background: #0f2640; }
        .btn-secondary {
            background: transparent;
            color: var(--accent);
            border: 1px solid var(--accent);
        }
        .btn-secondary:hover { background: var(--accent-light); }

        .label-divider {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 14px 0 10px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--text-muted);
        }
        .label-divider::before, .label-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        .checklist-item {
            display: grid;
            grid-template-columns: 1fr 120px 120px;
            align-items: center;
            gap: 16px;
            padding: 10px 0;
            border-bottom: 1px solid var(--border);
        }
        .checklist-item:last-child { border-bottom: none; }
        .checklist-item .item-text { font-size: 13px; }
        .checklist-item .item-text .item-letter {
            font-family: var(--font-mono);
            font-weight: 600;
            color: var(--accent);
            margin-right: 6px;
        }
        .check-col {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
        }
        .check-col .col-label {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: var(--text-muted);
        }
        .radio-group { display: flex; gap: 12px; justify-content: center; }

        .plazas-section { display: none; }
        .formato-section { display: block; }

        .plazas-toolbar {
            display: flex;
            gap: 12px;
            align-items: center;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }
        .search-input {
            flex: 1; min-width: 200px;
            border: 1px solid var(--border);
            padding: 8px 12px;
            font-family: var(--font-main);
            font-size: 13px;
            border-radius: 3px;
            background: var(--surface);
        }
        .search-input:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-light); }

        .plazas-table-wrap {
            background: var(--surface);
            border: 1px solid var(--border);
            overflow: auto;
            max-height: 560px;
        }
        table.plazas-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            min-width: 900px;
        }
        table.plazas-table thead th {
            background: var(--accent);
            color: white;
            padding: 9px 10px;
            text-align: left;
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            white-space: nowrap;
            position: sticky; top: 0;
        }
        table.plazas-table tbody td {
            padding: 7px 10px;
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }
        table.plazas-table tbody tr:hover td { background: var(--accent-light); }
        table.plazas-table .num { text-align: right; font-family: var(--font-mono); }
        table.plazas-table .tag {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 2px;
            font-size: 10px;
            font-family: var(--font-mono);
            font-weight: 600;
        }
        .tag-cat { background: var(--accent-light); color: var(--accent); }

        @media print {
            .page-header, .section-nav, .actions-bar, .plazas-section, .alert, .plazas-toolbar {
                display: none !important;
            }
            .formato-section form { display: none !important; }
            .print-document-only {
                display: block !important;
                margin: 0;
                padding: 0.5cm;
            }
            body { background: white; padding: 0; margin: 0; }
            .main-wrap { padding: 0; max-width: 100%; }
        }

        .print-document-only {
            display: none;
            font-family: 'Times New Roman', Times, serif;
            font-size: 11pt;
            line-height: 1.3;
        }
        .print-document-only .oficial-table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
            font-size: 10pt;
        }
        .print-document-only .oficial-table th,
        .print-document-only .oficial-table td {
            border: 1px solid black;
            padding: 6px 8px;
            vertical-align: top;
        }
        .print-document-only .oficial-table th {
            background: #f0f0f0;
            font-weight: bold;
            text-align: center;
        }
        .print-document-only .logo-area {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .print-document-only .firma-line {
            border-top: 1px solid black;
            margin-top: 30px;
            padding-top: 5px;
            text-align: center;
        }
        .print-document-only .radio-print {
            display: inline-block;
            margin: 0 8px;
        }
        .print-document-only .auth-section {
            margin: 15px 0;
            padding: 10px;
            border: 1px solid black;
        }

        @media (max-width: 700px) {
            .col-2, .col-3, .col-4, .col-2-1, .col-3-1, .col-rfc { grid-template-columns: 1fr; }
            .firma-grid { grid-template-columns: 1fr; }
            .auth-options { grid-template-columns: 1fr; }
            .checklist-item { grid-template-columns: 1fr; }
            .header-meta { display: none; }
        }
    </style>
</head>
<body>

<!-- ══ HEADER ══ -->
<header class="page-header">
    <div class="header-inner">
        <div class="header-logo">
            <img src="imagenes/tecnmlogo.png" alt="TecNM" style="height: 60px; width: auto;">
        </div>
        <div class="header-titles">
            <h1>Tecnológico Nacional de México</h1>
            <div class="sub">Departamento de Recursos Humanos — Compatibilidad de Empleos</div>
        </div>
        <div class="header-meta">
            Formato oficial<br>
            Artículos 136-137 RLFPRH<br>
            <?= date('d/m/Y') ?>
        </div>
    </div>
</header>

<!-- ══ NAV TABS ══ -->
<nav class="section-nav">
    <div class="nav-inner">
        <a class="nav-tab active" href="#" onclick="showTab('formato', this)">Solicitud de Compatibilidad</a>
        <a class="nav-tab" href="#" onclick="showTab('plazas', this)">Catálogo de Plazas</a>
    </div>
</nav>

<div class="main-wrap">

<?php if (!empty($errors)): ?>
<div class="alert alert-error">
    <span>⚠</span>
    <div><strong>Por favor corrija los siguientes errores:</strong>
        <ul><?php foreach($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
    </div>
</div>
<?php endif; ?>

<?php if ($submitted): ?>
<div class="alert alert-success">
    <span>✓</span>
    <div><strong><?= htmlspecialchars($mensaje_exito) ?></strong></div>
</div>
<?php endif; ?>

<!-- ══ SECCIÓN 1: FORMATO ══ -->
<div id="tab-formato" class="formato-section">
<form method="POST" action="">
<div class="formato-doc">

    <!-- Datos del solicitante -->
    <div class="doc-section">
        <div class="section-header">I. Datos del Servidor Público Solicitante</div>
        <div class="section-body">
            <div class="field-row col-rfc">
                <div class="field">
                    <label>Apellido Paterno <span class="req">*</span></label>
                    <input type="text" name="apellido_paterno" value="<?= htmlspecialchars($_POST['apellido_paterno'] ?? '') ?>" placeholder="Apellido paterno">
                </div>
                <div class="field">
                    <label>Apellido Materno</label>
                    <input type="text" name="apellido_materno" value="<?= htmlspecialchars($_POST['apellido_materno'] ?? '') ?>" placeholder="Apellido materno">
                </div>
                <div class="field">
                    <label>Nombre(s) <span class="req">*</span></label>
                    <input type="text" name="nombre" value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>" placeholder="Nombre(s)">
                </div>
                <div class="field">
                    <label>R.F.C. <span class="req">*</span></label>
                    <input type="text" name="rfc" value="<?= htmlspecialchars($_POST['rfc'] ?? $datos_rfc) ?>" placeholder="RFC" maxlength="13" style="font-family: var(--font-mono); letter-spacing:0.08em;">
                </div>
            </div>
            <div class="field-row col-1">
                <div class="field">
                    <label>Texto de solicitud</label>
                    <textarea name="texto_solicitud" rows="2"><?= htmlspecialchars($_POST['texto_solicitud'] ?? 'Atentamente solicito se autorice la Compatibilidad para desempeñar los siguientes puestos, cargos, comisiones o la prestación de servicios profesionales por honorarios, informando que el puesto que ocupo actualmente es:') ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <!-- Institución 1 -->
    <div class="doc-section">
        <div class="section-header">II. Institución 1 — Certifica los Datos del Puesto Actual</div>
        <div class="section-body">
            <div class="field-row col-1">
                <div class="field">
                    <label>Nombre de la Institución 1</label>
                    <input type="text" name="inst1_nombre" value="<?= htmlspecialchars($_POST['inst1_nombre'] ?? $institucion1) ?>">
                </div>
            </div>
            <div class="label-divider">Datos del puesto actual</div>
            <div class="field-row col-2-1">
                <div class="field">
                    <label>Puesto o Contrato <span class="req">*</span></label>
                    <input type="text" name="puesto_actual" value="<?= htmlspecialchars($_POST['puesto_actual'] ?? 'PROFESOR DE ASIGNATURA C (E.S.)') ?>">
                </div>
                <div class="field">
                    <label>Código Presupuestal</label>
                    <input type="text" name="codigo_presupuestal1" value="<?= htmlspecialchars($_POST['codigo_presupuestal1'] ?? 'E3525') ?>" style="font-family:var(--font-mono);">
                </div>
            </div>
            <div class="field-row col-2">
                <div class="field">
                    <label>Unidad de Adscripción / Centro de Trabajo</label>
                    <input type="text" name="unidad_adscripcion1" value="<?= htmlspecialchars($_POST['unidad_adscripcion1'] ?? 'INSTITUTO TECNOLÓGICO DE ENSENADA') ?>">
                </div>
                <div class="field">
                    <label>Tipo de Nombramiento</label>
                    <select name="tipo_nombramiento1" onchange="toggleFechaFin('inst1', this.value)">
                        <option value="10" <?= (($_POST['tipo_nombramiento1'] ?? '10') == '10') ? 'selected' : '' ?>>DEFINITIVO</option>
                        <option value="20" <?= (($_POST['tipo_nombramiento1'] ?? '') == '20') ? 'selected' : '' ?>>INTERINO</option>
                        <option value="30" <?= (($_POST['tipo_nombramiento1'] ?? '') == '30') ? 'selected' : '' ?>>HONORARIOS</option>
                        <option value="40" <?= (($_POST['tipo_nombramiento1'] ?? '') == '40') ? 'selected' : '' ?>>CONTRATO</option>
                    </select>
                </div>
            </div>
            <div class="field-row col-4">
                <div class="field">
                    <label>Fecha de Alta — Día</label>
                    <input type="number" name="alta_dia1" min="1" max="31" value="<?= htmlspecialchars($_POST['alta_dia1'] ?? '01') ?>" style="font-family:var(--font-mono);">
                </div>
                <div class="field">
                    <label>Mes</label>
                    <select name="alta_mes1">
                        <?php foreach($meses as $k=>$v): ?>
                        <option value="<?=$k?>" <?=(($_POST['alta_mes1'] ?? '09')==$k)?'selected':''?>><?=$v?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label>Año</label>
                    <input type="number" name="alta_ano1" min="1990" max="2099" value="<?= htmlspecialchars($_POST['alta_ano1'] ?? '2009') ?>" style="font-family:var(--font-mono);">
                </div>
                <div class="field">
                    <label>Remuneración ($)</label>
                    <input type="number" name="remuneracion1" step="0.01" value="<?= htmlspecialchars($_POST['remuneracion1'] ?? '3751.50') ?>" style="font-family:var(--font-mono);">
                </div>
            </div>
            <!-- Fecha Fin inst1 — solo si NO es Definitivo -->
            <div class="field-row col-3" id="fin-inst1" style="display:<?= (($_POST['tipo_nombramiento1'] ?? '10') != '10') ? 'grid' : 'none' ?>;">
                <div class="field">
                    <label>Fecha de Finalización — Día</label>
                    <input type="number" name="fin_dia1" min="1" max="31" value="<?= htmlspecialchars($_POST['fin_dia1'] ?? '') ?>" style="font-family:var(--font-mono);">
                </div>
                <div class="field">
                    <label>Mes</label>
                    <select name="fin_mes1">
                        <option value="">— Mes —</option>
                        <?php foreach($meses as $k=>$v): ?>
                        <option value="<?=$k?>" <?=(($_POST['fin_mes1'] ?? '')==$k)?'selected':''?>><?=$v?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label>Año</label>
                    <input type="number" name="fin_ano1" min="1990" max="2099" value="<?= htmlspecialchars($_POST['fin_ano1'] ?? '') ?>" style="font-family:var(--font-mono);">
                </div>
            </div>
            <div class="field-row col-2">
                <div class="field">
                    <label>Partida y Clave Presupuestal</label>
                    <input type="text" name="clave_presupuestal1" value="<?= htmlspecialchars($_POST['clave_presupuestal1'] ?? '11007') ?>" style="font-family:var(--font-mono);">
                </div>
                <div class="field">
                    <label>Clave Larga</label>
                    <input type="text" name="clave_larga1" value="<?= htmlspecialchars($_POST['clave_larga1'] ?? '1403E352506.0135201') ?>" style="font-family:var(--font-mono); font-size:12px;">
                </div>
            </div>
            <div class="field-row col-1">
                <div class="field">
                    <label>Ubicación del centro de trabajo, horario y tiempo de traslado</label>
                    <textarea name="ubicacion1"><?= htmlspecialchars($_POST['ubicacion1'] ?? 'BLVD. TECNOLÓGICO #150, EX EJIDO CHAPULTEPEC, ENSENADA, BAJA CALIFORNIA. LUNES DE 07:00 A 09:00, 12:00 A 14:00 Y DE 18:00 A 20:00 HRS.') ?></textarea>
                </div>
            </div>
            <div class="nota-legal" style="margin-top:12px;">
                (*) Los contratos de honorarios NO sujetos al artículo 131 del RLFPRH, únicamente deberán establecer las fechas de inicio y término del contrato, así como la(s) fecha(s) de entrega(s) parciales y/o totales de los productos o servicios correspondientes.
            </div>
        </div>
    </div>

    <!-- Institución 2 -->
    <div class="doc-section">
        <div class="section-header">III. Institución 2 — Valida los Datos del Puesto a Desempeñar</div>
        <div class="section-body">
            <div class="field-row col-1">
                <div class="field">
                    <label>Nombre de la Institución 2</label>
                    <input type="text" name="inst2_nombre" value="<?= htmlspecialchars($_POST['inst2_nombre'] ?? $institucion2) ?>">
                </div>
            </div>
            <div class="label-divider">Datos del puesto a desempeñar</div>
            <div class="field-row col-2-1">
                <div class="field">
                    <label>Puesto o Contrato</label>
                    <input type="text" name="puesto_nuevo" id="puesto_nuevo" value="<?= htmlspecialchars($_POST['puesto_nuevo'] ?? '') ?>">
                </div>
                <div class="field">
                    <label>Código Presupuestal</label>
                    <input type="text" name="codigo_presupuestal2" id="codigo_presupuestal2" value="<?= htmlspecialchars($_POST['codigo_presupuestal2'] ?? '') ?>" style="font-family:var(--font-mono);">
                </div>
            </div>
            <div class="field-row col-2">
                <div class="field">
                    <label>Unidad de Adscripción / Centro de Trabajo</label>
                    <input type="text" name="unidad_adscripcion2" value="<?= htmlspecialchars($_POST['unidad_adscripcion2'] ?? '') ?>">
                </div>
                <div class="field">
                    <label>Tipo de Nombramiento</label>
                    <select name="tipo_nombramiento2" onchange="toggleFechaFin('inst2', this.value)">
                        <option value="">— Seleccionar —</option>
                        <option value="10" <?= (($_POST['tipo_nombramiento2'] ?? '') == '10') ? 'selected' : '' ?>>DEFINITIVO</option>
                        <option value="20" <?= (($_POST['tipo_nombramiento2'] ?? '') == '20') ? 'selected' : '' ?>>INTERINO</option>
                        <option value="30" <?= (($_POST['tipo_nombramiento2'] ?? '') == '30') ? 'selected' : '' ?>>HONORARIOS</option>
                        <option value="40" <?= (($_POST['tipo_nombramiento2'] ?? '') == '40') ? 'selected' : '' ?>>CONTRATO</option>
                    </select>
                </div>
            </div>
            <div class="field-row col-4">
                <div class="field">
                    <label>Fecha Alta — Día</label>
                    <input type="number" name="alta_dia2" min="1" max="31" value="<?= htmlspecialchars($_POST['alta_dia2'] ?? '') ?>" style="font-family:var(--font-mono);">
                </div>
                <div class="field">
                    <label>Mes</label>
                    <select name="alta_mes2">
                        <option value="">—</option>
                        <?php foreach($meses as $k=>$v): ?>
                        <option value="<?=$k?>" <?=(($_POST['alta_mes2'] ?? '')==$k)?'selected':''?>><?=$v?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label>Año</label>
                    <input type="number" name="alta_ano2" min="1990" max="2099" value="<?= htmlspecialchars($_POST['alta_ano2'] ?? '') ?>" style="font-family:var(--font-mono);">
                </div>
                <div class="field">
                    <label>Remuneración ($)</label>
                    <input type="number" name="remuneracion2" id="remuneracion2" step="0.01" value="<?= htmlspecialchars($_POST['remuneracion2'] ?? '') ?>" style="font-family:var(--font-mono);">
                </div>
            </div>
            <!-- Fecha Fin inst2 — solo si NO es Definitivo -->
            <div class="field-row col-3" id="fin-inst2" style="display:<?= (!empty($_POST['tipo_nombramiento2']) && $_POST['tipo_nombramiento2'] != '10') ? 'grid' : 'none' ?>; margin-top:12px;">
                <div class="field">
                    <label>Fecha de Finalización — Día</label>
                    <input type="number" name="fin_dia2" min="1" max="31" value="<?= htmlspecialchars($_POST['fin_dia2'] ?? '') ?>" style="font-family:var(--font-mono);">
                </div>
                <div class="field">
                    <label>Mes</label>
                    <select name="fin_mes2">
                        <option value="">— Mes —</option>
                        <?php foreach($meses as $k=>$v): ?>
                        <option value="<?=$k?>" <?=(($_POST['fin_mes2'] ?? '')==$k)?'selected':''?>><?=$v?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label>Año</label>
                    <input type="number" name="fin_ano2" min="1990" max="2099" value="<?= htmlspecialchars($_POST['fin_ano2'] ?? '') ?>" style="font-family:var(--font-mono);">
                </div>
            </div>
            <!-- CAMBIO 1: agregados campos Partida/Clave Presupuestal y Clave Larga igual que inst1 -->
        <div class="field-row col-2">
            <div class="field">
                <label>Partida y Clave Presupuestal</label>
                <input type="text" name="clave_presupuestal2" id="clave_presupuestal2" value="<?= htmlspecialchars($_POST['clave_presupuestal2'] ?? '') ?>" style="font-family:var(--font-mono);">
            </div>
            <div class="field">
                <label>Clave Larga</label>
                <input type="text" name="clave_larga2" id="clave_larga2" value="<?= htmlspecialchars($_POST['clave_larga2'] ?? '') ?>" style="font-family:var(--font-mono); font-size:12px;">
            </div>
        </div>
            <div class="field-row col-1">
                <div class="field">
                    <label>Ubicación del centro de trabajo, horario y tiempo de traslado</label>
                    <textarea name="ubicacion2" id="ubicacion2"><?= htmlspecialchars($_POST['ubicacion2'] ?? '') ?></textarea>
                </div>
            </div>
            <div class="nota-legal" style="margin-top:12px;">
                (*) Los contratos de honorarios NO sujetos al artículo 131 del RLFPRH, únicamente deberán establecer las fechas de inicio y término del contrato, así como la(s) fecha(s) de entrega(s) parciales y/o totales de los productos o servicios correspondientes.
            </div>
        </div>
    </div>

    <!-- Lugar y Fecha -->
    <div class="doc-section">
        <div class="section-body">
            <div class="field-row" style="grid-template-columns: 1fr 60px 120px 60px 100px; align-items: end;">
                <div class="field">
                    <label>Lugar</label>
                    <input type="text" name="lugar" value="<?= htmlspecialchars($_POST['lugar'] ?? 'ENSENADA') ?>">
                </div>
                <div class="field">
                    <label>Día</label>
                    <input type="number" name="fecha_dia" min="1" max="31" value="<?= htmlspecialchars($_POST['fecha_dia'] ?? date('d')) ?>" style="font-family:var(--font-mono); text-align:center;">
                </div>
                <div class="field">
                    <label>Mes</label>
                    <select name="fecha_mes">
                        <?php foreach($meses as $k=>$v): ?>
                        <option value="<?=$v?>" <?=(($_POST['fecha_mes'] ?? date('F'))==$v)?'selected':''?>><?=$v?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label>de</label>
                    <input type="text" readonly value="de" style="text-align:center; color:var(--text-muted);">
                </div>
                <div class="field">
                    <label>Año</label>
                    <input type="number" name="fecha_ano" value="<?= htmlspecialchars($_POST['fecha_ano'] ?? date('Y')) ?>" style="font-family:var(--font-mono); text-align:center;">
                </div>
            </div>
        </div>
    </div>

    <!-- Firmas -->
    <div class="doc-section">
        <div class="section-body">
            <div class="firma-grid">
                <div class="firma-box">
                    <div class="firma-inst">Certificó — <?= htmlspecialchars($_POST['inst1_nombre'] ?? $institucion1) ?></div>
                    <div class="firma-line"></div>
                    <div class="firma-rol">Director de Personal</div>
                    <input class="firma-nombre-input" type="text" name="firma_cert_nombre" value="<?= htmlspecialchars($_POST['firma_cert_nombre'] ?? 'LIC. JAVIER MUÑOZ DUEÑAS') ?>" placeholder="Nombre y Firma">
                </div>
                <div class="firma-box">
                    <div class="firma-inst">Validó / Autorizó — <?= htmlspecialchars($_POST['inst2_nombre'] ?? $institucion2) ?></div>
                    <div class="firma-line"></div>
                    <div class="firma-rol">Director de Personal</div>
                    <input class="firma-nombre-input" type="text" name="firma_val_nombre" value="<?= htmlspecialchars($_POST['firma_val_nombre'] ?? 'LIC. JAVIER MUÑOZ DUEÑAS') ?>" placeholder="Nombre y Firma">
                </div>
            </div>
            <div style="margin-top:10px; font-size:11px; color:var(--text-muted); text-align:center;">
                * En caso que el dictamen corresponda a la DGDHO, este formato deberá tener anexo el oficio correspondiente.
            </div>
        </div>
    </div>

    <!-- Resolución -->
    <div class="doc-section">
        <div class="section-header">IV. Resolución de Compatibilidad</div>
        <div class="section-body">
            <div class="auth-options">
                <div class="auth-option" id="opt-a">
                    <label>
                        <input type="radio" name="resolucion" value="A" <?= (($_POST['resolucion'] ?? 'A') == 'A') ? 'checked' : '' ?> onchange="selectOpt('a')">
                        <div>
                            <div class="opt-title">a) SE OTORGA LA AUTORIZACIÓN</div>
                            <div class="opt-desc">De conformidad con lo dispuesto en los artículos 136 y 137 del Reglamento de la Ley Federal de Presupuesto y Responsabilidad Hacendaria.</div>
                        </div>
                    </label>
                </div>
                <div class="auth-option" id="opt-b">
                    <label>
                        <input type="radio" name="resolucion" value="B" <?= (($_POST['resolucion'] ?? '') == 'B') ? 'checked' : '' ?> onchange="selectOpt('b')">
                        <div>
                            <div class="opt-title">b) NO SE OTORGA LA AUTORIZACIÓN</div>
                            <div class="opt-desc">Debido a que no reúne los requisitos establecidos en el Reglamento de la Ley Federal de Presupuesto y Responsabilidad Hacendaria.</div>
                        </div>
                    </label>
                </div>
            </div>
            <div id="fechas-auth" style="margin-top:14px; display: <?= (($_POST['resolucion'] ?? 'A') == 'A') ? 'block' : 'none' ?>;">
                <div class="field-row col-2">
                    <div class="field">
                        <label>Válida a partir del (Día/Mes/Año)</label>
                        <input type="date" name="auth_desde" value="<?= htmlspecialchars($_POST['auth_desde'] ?? date('Y-m-d')) ?>">
                    </div>
                    <div class="field">
                        <label>Hasta (Día/Mes/Año)</label>
                        <input type="date" name="auth_hasta" value="<?= htmlspecialchars($_POST['auth_hasta'] ?? date('Y-m-d', strtotime('+1 year'))) ?>">
                    </div>
                </div>
            </div>
            <p style="font-size:11px; margin-top:12px; color:var(--text-muted);">
                <strong>NOTA:</strong> Este documento deberá contar con el sello de ambas instituciones.
            </p>
        </div>
    </div>

    <!-- Lista Checable -->
    <div class="doc-section">
        <div class="section-header">V. Lista Checable</div>
        <div class="section-body">
            <div style="margin-bottom:16px;">
                <div class="section-header alt">I. Se hace constar que:</div>
                <div style="padding:12px 0;">
                    <?php
                    $checklist_i = [
                        'ci1' => 'Se cuenta con la descripción y perfil del puesto que el solicitante ocupa actualmente.',
                        'ci2' => 'Se cuenta con la descripción y perfil del puesto que se pretende ocupar.',
                    ];
                    foreach($checklist_i as $key => $label): ?>
                    <div class="checklist-item">
                        <div class="item-text"><?= $label ?></div>
                        <div class="check-col">
                            <span class="col-label">Institución 1</span>
                            <div class="radio-group">
                                <label class="radio-label"><input type="radio" name="<?=$key?>_i1" value="si" <?= (($_POST[$key.'_i1'] ?? '') == 'si') ? 'checked':'' ?>> Sí</label>
                                <label class="radio-label"><input type="radio" name="<?=$key?>_i1" value="no" <?= (($_POST[$key.'_i1'] ?? '') == 'no') ? 'checked':'' ?>> No</label>
                            </div>
                        </div>
                        <div class="check-col">
                            <span class="col-label">Institución 2</span>
                            <div class="radio-group">
                                <label class="radio-label"><input type="radio" name="<?=$key?>_i2" value="si" <?= (($_POST[$key.'_i2'] ?? '') == 'si') ? 'checked':'' ?>> Sí</label>
                                <label class="radio-label"><input type="radio" name="<?=$key?>_i2" value="no" <?= (($_POST[$key.'_i2'] ?? '') == 'no') ? 'checked':'' ?>> No</label>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div style="margin-bottom:16px;">
                <div class="section-header alt">II. Las funciones a desarrollar en los puestos:</div>
                <div style="padding:12px 0;">
                    <?php
                    $checklist_ii = [
                        'iia' => ['a)', '¿Son excluyentes entre sí?'],
                        'iib' => ['b)', '¿Implican o pudieran originar conflicto de intereses?'],
                    ];
                    foreach($checklist_ii as $key => [$letter, $label]): ?>
                    <div class="checklist-item">
                        <div class="item-text"><span class="item-letter"><?=$letter?></span><?=$label?></div>
                        <div class="check-col">
                            <span class="col-label">Institución 1</span>
                            <div class="radio-group">
                                <label class="radio-label"><input type="radio" name="<?=$key?>_i1" value="si" <?= (($_POST[$key.'_i1'] ?? '') == 'si') ? 'checked':'' ?>> Sí</label>
                                <label class="radio-label"><input type="radio" name="<?=$key?>_i1" value="no" <?= (($_POST[$key.'_i1'] ?? '') == 'no') ? 'checked':'' ?>> No</label>
                            </div>
                        </div>
                        <div class="check-col">
                            <span class="col-label">Institución 2</span>
                            <div class="radio-group">
                                <label class="radio-label"><input type="radio" name="<?=$key?>_i2" value="si" <?= (($_POST[$key.'_i2'] ?? '') == 'si') ? 'checked':'' ?>> Sí</label>
                                <label class="radio-label"><input type="radio" name="<?=$key?>_i2" value="no" <?= (($_POST[$key.'_i2'] ?? '') == 'no') ? 'checked':'' ?>> No</label>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div>
                <div class="section-header alt">III. Existe la posibilidad de desempeñar los puestos adecuadamente en razón de:</div>
                <div style="padding:12px 0;">
                    <?php
                    $checklist_iii = [
                        'iiia' => ['* a)', 'El horario y jornada de trabajo que a cada puesto corresponde.'],
                        'iiib' => ['b)', 'Las particularidades, características, exigencias y condiciones de los puestos de que se trate.'],
                        'iiic' => ['* c)', 'La ubicación de los centros de trabajo y del domicilio del servidor público.'],
                        'iiid' => ['d)', '¿El servidor público manifestó expresamente no contar con licencia (con o sin goce de sueldo)?'],
                        'iiie' => ['e)', '¿Existe prohibición legal o contractual para emitir la compatibilidad?'],
                        'iiif' => ['f)', 'Las remuneraciones a percibir con la presente compatibilidad rebasan el límite establecido en el art. 127 de la Constitución Política...'],
                        'iiig' => ['g)', '¿Se trata de un trabajo técnico calificado o de alta especialización?'],
                        'iiih' => ['h)', 'El número de horas en actividades o funciones docentes, si son frente a grupo o están referidas a las categorías directiva o de supervisión.'],
                    ];
                    foreach($checklist_iii as $key => [$letter, $label]): ?>
                    <div class="checklist-item">
                        <div class="item-text"><span class="item-letter"><?=$letter?></span><?=$label?></div>
                        <div class="check-col">
                            <span class="col-label">Institución 1</span>
                            <div class="radio-group">
                                <label class="radio-label"><input type="radio" name="<?=$key?>_i1" value="si" <?= (($_POST[$key.'_i1'] ?? '') == 'si') ? 'checked':'' ?>> Sí</label>
                                <label class="radio-label"><input type="radio" name="<?=$key?>_i1" value="no" <?= (($_POST[$key.'_i1'] ?? '') == 'no') ? 'checked':'' ?>> No</label>
                            </div>
                        </div>
                        <div class="check-col">
                            <span class="col-label">Institución 2</span>
                            <div class="radio-group">
                                <label class="radio-label"><input type="radio" name="<?=$key?>_i2" value="si" <?= (($_POST[$key.'_i2'] ?? '') == 'si') ? 'checked':'' ?>> Sí</label>
                                <label class="radio-label"><input type="radio" name="<?=$key?>_i2" value="no" <?= (($_POST[$key.'_i2'] ?? '') == 'no') ? 'checked':'' ?>> No</label>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Analista -->
    <div class="doc-section">
        <div class="section-header alt">Datos del Analista</div>
        <div class="section-body">
            <div class="field-row col-3">
                <div class="field">
                    <label>Fecha de análisis</label>
                    <input type="date" name="fecha_analisis" value="<?= htmlspecialchars($_POST['fecha_analisis'] ?? date('Y-m-d')) ?>">
                </div>
                <div class="field">
                    <label>Nombre del Analista</label>
                    <input type="text" name="nombre_analista" value="<?= htmlspecialchars($_POST['nombre_analista'] ?? 'CHRISTIAN GUILLERMO HERNÁNDEZ HERNÁNDEZ') ?>">
                </div>
                <div class="field">
                    <label>Puesto del Analista</label>
                    <input type="text" name="puesto_analista" value="<?= htmlspecialchars($_POST['puesto_analista'] ?? 'JEFE DEL DEPARTAMENTO DE RECURSOS HUMANOS') ?>">
                </div>
            </div>
            <div class="field-row col-2">
                <div class="field">
                    <label>Director del Plantel — Nombre y Firma</label>
                    <input type="text" name="director_plantel" value="<?= htmlspecialchars($_POST['director_plantel'] ?? 'VALENTÍN ARQUÍMEDES SÁNCHEZ BELTRÁN') ?>">
                </div>
                <div class="field">
                    <label>Firma del Analista (referencia)</label>
                    <input type="text" name="firma_analista" value="<?= htmlspecialchars($_POST['firma_analista'] ?? '') ?>" placeholder="Nombre para referencia">
                </div>
            </div>
        </div>
    </div>

    <!-- Botones -->
    <div class="actions-bar">
        <button type="button" class="btn btn-secondary" onclick="window.print()">🖨 Imprimir</button>
        <button type="reset" class="btn btn-secondary">↺ Limpiar</button>
        <button type="submit" class="btn btn-primary">✓ Guardar Solicitud</button>
    </div>

</div><!-- end formato-doc -->
</form>
</div><!-- end tab-formato -->

<!-- ══ SECCIÓN 2: CATÁLOGO DE PLAZAS ══ -->
<div id="tab-plazas" class="plazas-section">
    <div style="margin-bottom:16px;">
        <h2 style="font-size:16px; font-weight:600; color:var(--accent); margin-bottom:4px;">Catálogo de Plazas — Instituto Tecnológico de Ensenada</h2>
        <p style="font-size:13px; color:var(--text-muted);">Plazas disponibles para compatibilidad. Haga clic en una plaza para usar sus datos en la solicitud.</p>
    </div>
    <div class="plazas-toolbar">
        <input class="search-input" type="text" id="plazas-search" placeholder="Buscar por puesto, categoría o clave…" oninput="filterPlazas(this.value)">
        <select id="cat-filter" style="padding:8px 12px; border:1px solid var(--border); border-radius:3px; font-size:13px; font-family:var(--font-main); background:white;" onchange="filterPlazas(document.getElementById('plazas-search').value)">
            <option value="">Todas las categorías</option>
            <option value="E3507">E3507 — Técnico Docente B</option>
            <option value="E3509">E3509 — Técnico Docente C</option>
            <option value="E3519">E3519 — Profesor Asignatura A</option>
            <option value="E3521">E3521 — Profesor Asignatura B</option>
            <option value="E3525">E3525 — Profesor Asignatura C</option>
        </select>
        <span id="plazas-count" style="font-size:12px; color:var(--text-muted); white-space:nowrap;"></span>
    </div>
    <div class="plazas-table-wrap">
        <table class="plazas-table" id="plazas-table">
            <thead>
                <tr>
                    <th>CT</th>
                    <th>Categoría</th>
                    <th>Descripción</th>
                    <th>CD Trabajo</th>
                    <th>Salario</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody id="plazas-tbody">
                <?php foreach($plazas as $plaza): ?>
                <tr data-cat="<?= htmlspecialchars($plaza['Categoria']) ?>" data-search="<?= strtolower($plaza['Categoria'] . ' ' . $plaza['Des_Categoria']) ?>">
                    <td><span style="font-family:var(--font-mono);"><?= htmlspecialchars($plaza['Clave_Presupuestal']) ?></span></td>
                    <td><span class="tag tag-cat"><?= htmlspecialchars($plaza['Categoria']) ?></span></td>
                    <td><?= htmlspecialchars($plaza['Des_Categoria']) ?></td>
                    <td><?= htmlspecialchars($plaza['CD_Trabajo']) ?></td>
                    <td class="num" style="font-weight:700; color:var(--success);">$<?= number_format($plaza['Salario'], 2) ?></td>
                    <td>
                        <button type="button" style="padding:4px 10px; font-size:11px; background:var(--accent); color:white; border:none; border-radius:2px; cursor:pointer;"
                            onclick="usarPlaza('<?= htmlspecialchars($plaza['Des_Categoria'], ENT_QUOTES) ?>', '<?= htmlspecialchars($plaza['Categoria'], ENT_QUOTES) ?>', '<?= htmlspecialchars($plaza['Clave_Presupuestal'], ENT_QUOTES) ?>', <?= $plaza['Salario'] ?>)">
                            Usar →
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div style="margin-top:8px; font-size:11px; color:var(--text-muted);">
        Total de plazas registradas: <?= count($plazas) ?>
    </div>
</div>

</div><!-- end main-wrap -->

<!-- ══ DOCUMENTO PARA IMPRESIÓN ══ -->
<div class="print-document-only">
    <div class="logo-area">
        <div><img src="imagenes/logotec.png" alt="TecNM" style="max-width:100px; max-height:80px;"></div>
        <div style="text-align:center;">
            <h3>SECRETARÍA DE EDUCACIÓN PÚBLICA</h3>
            <h2>TECNOLÓGICO NACIONAL DE MÉXICO</h2>
            <p>Dirección de Personal · Formato de Compatibilidad</p>
        </div>
        <div><img src="imagenes/seplogo.jpg" alt="SEP" style="max-width:100px; max-height:80px;"></div>
    </div>

    <h4>SOLICITUD DE COMPATIBILIDAD</h4>
    <p><strong>Atentamente solicito se autorice la Compatibilidad para desempeñar los siguientes puestos, cargos, comisiones o la prestación de servicios profesionales por honorarios, informando que el puesto que ocupo actualmente es:</strong></p>

    <h4>Institución 1 que certifica los datos del puesto actual</h4>
    <table class="oficial-table">
        <thead><tr><th>Puesto o Contrato</th><th>Código presupuestal</th><th>Unidad de Adscripción</th><th>Fecha de Alta</th><th>Tipo Nombramiento</th><th>Remuneración</th><th>Ubicación, horario y tiempo de traslado</th></tr></thead>
        <tbody>
            <tr>
                <td id="print_puesto_inst1"></td>
                <td id="print_codigo_inst1"></td>
                <td id="print_unidad_inst1"></td>
                <td id="print_fecha_inst1"></td>
                <td id="print_tipo_inst1"></td>
                <td id="print_rem_inst1"></td>
                <td id="print_ubicacion_inst1"></td>
            </tr>
        </tbody>
    </table>

    <h4>Institución 2 que valida los datos del puesto o contrato a desempeñar</h4>
    <table class="oficial-table">
        <thead><tr><th>Puesto o Contrato</th><th>Código presupuestal</th><th>Unidad de Adscripción</th><th>Remuneración</th><th>Ubicación, horario y tiempo de traslado</th></tr></thead>
        <tbody>
            <tr>
                <td id="print_puesto_inst2"></td>
                <td id="print_codigo_inst2"></td>
                <td id="print_unidad_inst2"></td>
                <td id="print_rem_inst2"></td>
                <td id="print_ubicacion_inst2"></td>
            </tr>
        </tbody>
    </table>

    <div style="margin:20px 0; text-align:right;" id="print_lugar_fecha"></div>

    <h4>LISTA CHECABLE</h4>
    <div class="checklist-print">
        <strong>I. SE HACE CONSTAR QUE:</strong><br>
        ✓ Se cuenta con la descripción y perfil del puesto que el solicitante ocupa actualmente.<br>
        ✓ Se cuenta con la descripción y perfil del puesto que se pretende ocupar.<br><br>
        <strong>II. LAS FUNCIONES A DESARROLLAR EN LOS PUESTOS:</strong><br>
        a) ¿Son excluyentes entre sí? <span class="radio-print">☐ Sí</span> <span class="radio-print">☒ No</span><br>
        b) ¿Implican o pudieran originar conflicto de intereses? <span class="radio-print">☐ Sí</span> <span class="radio-print">☒ No</span><br><br>
        <strong>III. ¿Existe la posibilidad de desempeñar los puestos ADECUADAMENTE EN RAZÓN DE:</strong><br>
        a) El horario y jornada de trabajo: <span class="radio-print">☒ Sí</span> <span class="radio-print">☐ No</span><br>
        b) Las particularidades y condiciones de los puestos: <span class="radio-print">☒ Sí</span> <span class="radio-print">☐ No</span><br>
        c) La ubicación de los centros de trabajo: <span class="radio-print">☒ Sí</span> <span class="radio-print">☐ No</span><br>
        d) ¿El servidor público no cuenta con licencia? <span class="radio-print">☒ Sí</span> <span class="radio-print">☐ No</span><br>
        e) ¿Existe prohibición legal o contractual? <span class="radio-print">☐ Sí</span> <span class="radio-print">☒ No</span><br>
        f) Las remuneraciones rebasan el límite del art. 127: <span class="radio-print">☐ Sí</span> <span class="radio-print">☒ No</span><br>
        g) ¿Se trata de trabajo técnico calificado? <span class="radio-print">☒ Sí</span> <span class="radio-print">☐ No</span><br>
        h) Horas en actividades docentes frente a grupo.<br><br>
    </div>

    <div style="margin:30px 0;">
        <table style="width:100%; border:none;">
            <tr>
                <td style="text-align:center; width:50%">
                    <div class="firma-line" id="print_firma_cert"></div>
                    <small>DIRECTOR DE PERSONAL</small>
                </td>
                <td style="text-align:center;">
                    <div class="firma-line" id="print_firma_director"></div>
                    <small>DIRECTOR DEL PLANTEL</small>
                </td>
            </tr>
        </table>
    </div>

    <div class="auth-section" id="print_resolucion_a">
        a) De conformidad con lo dispuesto en los artículos 136 y 137 del RLFPRH, se otorga la presente AUTORIZACIÓN de Compatibilidad.
    </div>
    <div class="auth-section" id="print_resolucion_b" style="display:none;">
        b) NO SE OTORGA LA AUTORIZACIÓN de Compatibilidad.
    </div>

    <p style="font-size:9pt; margin-top:20px;"><strong>NOTA:</strong> Este documento deberá contar con el sello de ambas instituciones.</p>
    <p style="font-size:9pt;" id="print_analista_fecha"></p>
</div>

<script>
function showTab(name, el) {
    event.preventDefault();
    document.getElementById('tab-formato').style.display = name === 'formato' ? 'block' : 'none';
    document.getElementById('tab-plazas').style.display = name === 'plazas' ? 'block' : 'none';
    document.querySelectorAll('.nav-tab').forEach(t => t.classList.remove('active'));
    el.classList.add('active');
    if (name === 'plazas') updateCount();
}

function selectOpt(which) {
    document.getElementById('opt-a').classList.toggle('selected', which === 'a');
    document.getElementById('opt-b').classList.toggle('selected', which === 'b');
    document.getElementById('fechas-auth').style.display = which === 'a' ? 'block' : 'none';
}

(function(){
    const checked = document.querySelector('input[name="resolucion"]:checked');
    if (checked) selectOpt(checked.value.toLowerCase());
})();

function toggleFechaFin(inst, valor) {
    const bloque = document.getElementById('fin-' + inst);
    if (!bloque) return;
    bloque.style.display = (valor !== '10' && valor !== '') ? 'grid' : 'none';
}

function filterPlazas(query) {
    query = query.toLowerCase();
    const catFilter = document.getElementById('cat-filter').value;
    const rows = document.querySelectorAll('#plazas-tbody tr');
    let visible = 0;
    rows.forEach(row => {
        const matchSearch = !query || row.dataset.search.includes(query);
        const matchCat = !catFilter || row.dataset.cat === catFilter;
        row.style.display = (matchSearch && matchCat) ? '' : 'none';
        if (matchSearch && matchCat) visible++;
    });
    document.getElementById('plazas-count').textContent = visible + ' plazas encontradas';
}

function updateCount() {
    const rows = document.querySelectorAll('#plazas-tbody tr');
    document.getElementById('plazas-count').textContent = rows.length + ' plazas encontradas';
}

function usarPlaza(puesto, cat, clave, rem) {
    document.querySelector('input[name="puesto_nuevo"]').value = puesto;
    document.querySelector('input[name="codigo_presupuestal2"]').value = cat;
    document.querySelector('input[name="clave_presupuestal1"]').value = clave;
    document.querySelector('input[name="remuneracion2"]').value = rem;
    document.getElementById('print_puesto_inst2').innerText = puesto;
    document.getElementById('print_codigo_inst2').innerText = cat;
    document.getElementById('print_rem_inst2').innerText = '$' + parseFloat(rem).toLocaleString('en-US');
    showTab('formato', document.querySelector('.nav-tab'));
    document.querySelector('.nav-tab').classList.add('active');
    setTimeout(() => {
        const el = document.querySelector('input[name="puesto_nuevo"]');
        el.scrollIntoView({behavior:'smooth', block:'center'});
        el.focus();
        el.style.transition = 'background 0.5s';
        el.style.background = '#fffde7';
        setTimeout(() => el.style.background = '', 1500);
    }, 200);
}

function actualizarDatosImpresion() {
    const get = (sel) => document.querySelector(sel)?.value || '';
    const getSelect = (sel) => { const s = document.querySelector(sel); return s?.options[s.selectedIndex]?.text || ''; };

    // CAMBIO 2: función auxiliar — si el campo está vacío muestra "campo vacío"
    const val = (selector) => get(selector).trim() || 'campo vacío';

    // Institución 1
    document.getElementById('print_puesto_inst1').innerText = val('input[name="puesto_actual"]'); // CAMBIO 2
    document.getElementById('print_codigo_inst1').innerText = val('input[name="codigo_presupuestal1"]'); // CAMBIO 2
    document.getElementById('print_unidad_inst1').innerText = val('input[name="unidad_adscripcion1"]'); // CAMBIO 2
    document.getElementById('print_fecha_inst1').innerText = get('input[name="alta_dia1"]') + '/' + getSelect('select[name="alta_mes1"]') + '/' + get('input[name="alta_ano1"]');
    document.getElementById('print_tipo_inst1').innerText = getSelect('select[name="tipo_nombramiento1"]');
    document.getElementById('print_rem_inst1').innerText = '$' + parseFloat(get('input[name="remuneracion1"]') || 0).toLocaleString('en-US');
    document.getElementById('print_ubicacion_inst1').innerText = val('textarea[name="ubicacion1"]'); // CAMBIO 2

    // Institución 2
    document.getElementById('print_puesto_inst2').innerText = val('input[name="puesto_nuevo"]'); // CAMBIO 2
    document.getElementById('print_codigo_inst2').innerText = val('input[name="codigo_presupuestal2"]'); // CAMBIO 2
    document.getElementById('print_unidad_inst2').innerText = val('input[name="unidad_adscripcion2"]'); // CAMBIO 2
    document.getElementById('print_rem_inst2').innerText = '$' + parseFloat(get('input[name="remuneracion2"]') || 0).toLocaleString('en-US');
    document.getElementById('print_ubicacion_inst2').innerText = val('textarea[name="ubicacion2"]'); // CAMBIO 2

    // Lugar y fecha
    const lugar = get('input[name="lugar"]');
    const dia = get('input[name="fecha_dia"]');
    const mes = getSelect('select[name="fecha_mes"]');
    const ano = get('input[name="fecha_ano"]');
    document.getElementById('print_lugar_fecha').innerHTML = lugar + ' a ' + dia + ' de ' + mes + ' de ' + ano;


    // Firmas 
    document.getElementById('print_firma_cert').innerHTML = get('input[name="firma_cert_nombre"]') + '<br><small>DIRECTOR DE PERSONAL — ' + get('input[name="inst1_nombre"]') + '</small>'; // CAMBIO 1
    document.getElementById('print_firma_director').innerHTML = get('input[name="director_plantel"]') + '<br><small>DIRECTOR DEL PLANTEL — ' + get('input[name="inst2_nombre"]') + '</small>'; // CAMBIO 1

    // Analista
    document.getElementById('print_analista_fecha').innerHTML = 'Fecha de análisis: ' + get('input[name="fecha_analisis"]') + ' &nbsp;&nbsp; Analista: ' + get('input[name="nombre_analista"]');

    // Resolución
    const res = document.querySelector('input[name="resolucion"]:checked')?.value;
    document.getElementById('print_resolucion_a').style.display = res === 'A' ? 'block' : 'none';
    document.getElementById('print_resolucion_b').style.display = res === 'B' ? 'block' : 'none';
}

window.onbeforeprint = actualizarDatosImpresion;

window.addEventListener('DOMContentLoaded', () => {
    document.getElementById('tab-plazas').style.display = 'none';
    actualizarDatosImpresion();
    updateCount();
});
</script>
</body>
</html>