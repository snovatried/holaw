<?php
session_start();
require '../config/conexion.php';

if (!isset($_SESSION['rol'])) {
    header('Location: ../index.php');
    exit;
}

$rol = $_SESSION['rol'];
$idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);
$titulo = 'Bienvenido';
$acciones = [];
$proximos = [];
$mostrarPacientes = false;
$modoLegacy = false;
$mensajeInfo = '';

$stmtUsuario = $pdo->prepare('SELECT nombre FROM usuarios WHERE id_usuario = ? LIMIT 1');
$stmtUsuario->execute([$idUsuario]);
$nombreUsuario = (string) ($stmtUsuario->fetchColumn() ?: 'Usuario');
$checkColumn = $pdo->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ? LIMIT 1');
$checkColumn->execute(['public', 'programacion', 'duracion_dias']);
$hasDuracionDias = (bool) $checkColumn->fetchColumn();
$duracionSelect = $hasDuracionDias ? 'p.duracion_dias' : 'NULL::INTEGER AS duracion_dias';

if ($rol === 'admin') {
    $titulo = "Bienvenido {$nombreUsuario}";
    $mensajeInfo = 'Desde aquí puedes administrar usuarios, asignaciones y programación de dispensos.';
    $acciones = [
        ['href' => '../medicamentos/listar.php', 'titulo' => 'Listar medicamentos', 'desc' => 'Consulta y revisa los medicamentos disponibles.'],
        ['href' => '../medicamentos/agregar.php', 'titulo' => 'Agregar medicamento', 'desc' => 'Registra nuevos medicamentos en el sistema.'],
        ['href' => '../programacion/crear.php', 'titulo' => 'Programar dispensos', 'desc' => 'Configura horarios y cantidades de dispenso.'],
        ['href' => '../asignaciones/gestionar.php', 'titulo' => 'Asignar pacientes', 'desc' => 'Vincula pacientes con cuidadores.'],
        ['href' => '../usuarios/crear.php', 'titulo' => 'Crear usuarios', 'desc' => 'Permite al administrador registrar nuevos usuarios.'],
        ['href' => '../usuarios/configurar_correos.php', 'titulo' => 'Configurar correos', 'desc' => 'Ajusta correos de notificación y pruebas de envío.'],
        ['href' => '../historial/ver.php', 'titulo' => 'Ver historial', 'desc' => 'Revisa dispensos realizados y eventos previos.'],
    ];
} elseif ($rol === 'cuidador') {
    $titulo = "Bienvenido {$nombreUsuario}";
    $mensajeInfo = 'Aquí puedes revisar pacientes, horarios y ajustar correos de notificación.';
    $acciones = [
        ['href' => '../medicamentos/listar.php', 'titulo' => 'Listar medicamentos', 'desc' => 'Explora los medicamentos disponibles para tus pacientes.'],
        ['href' => '../medicamentos/agregar.php', 'titulo' => 'Agregar medicamento', 'desc' => 'Carga medicamentos nuevos cuando sea necesario.'],
        ['href' => '../programacion/crear.php', 'titulo' => 'Programar medicamentos', 'desc' => 'Define la rutina de dispenso de cada paciente.'],
        ['href' => '../historial/ver.php', 'titulo' => 'Ver historial', 'desc' => 'Consulta los registros de dispensos anteriores.'],
        ['href' => '../usuarios/configurar_correos.php', 'titulo' => 'Configurar correos', 'desc' => 'Define correos de notificación para tus pacientes.'],
    ];

    $checkColumn->execute(['public', 'programacion', 'id_paciente']);
    $hasIdPaciente = (bool) $checkColumn->fetchColumn();

    $hasRelTable = (bool) $pdo->query("SELECT to_regclass('public.cuidadores_pacientes') IS NOT NULL")->fetchColumn();
    $modoLegacy = !$hasIdPaciente || !$hasRelTable;
    $mostrarPacientes = true;

    if ($modoLegacy) {
        $sql = "
            SELECT p.hora_dispenso, {$duracionSelect}, p.cantidad, m.nombre AS medicamento, m.dosis, 'Mi programación' AS paciente
            FROM programacion p
            JOIN medicamentos m ON p.id_medicamento = m.id_medicamento
            WHERE p.id_usuario = ? AND p.estado = 'activo'
            ORDER BY CASE WHEN p.hora_dispenso >= CURRENT_TIME THEN 0 ELSE 1 END, p.hora_dispenso ASC
            LIMIT 10
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$idUsuario]);
    } else {
        $sql = "
            SELECT p.hora_dispenso, {$duracionSelect}, p.cantidad, m.nombre AS medicamento, m.dosis, u.nombre AS paciente
            FROM programacion p
            JOIN medicamentos m ON p.id_medicamento = m.id_medicamento
            JOIN cuidadores_pacientes cp ON cp.id_paciente = p.id_paciente
            JOIN usuarios u ON u.id_usuario = p.id_paciente
            WHERE cp.id_cuidador = ? AND p.estado = 'activo'
            ORDER BY CASE WHEN p.hora_dispenso >= CURRENT_TIME THEN 0 ELSE 1 END, p.hora_dispenso ASC
            LIMIT 10
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$idUsuario]);
    }

    $proximos = $stmt->fetchAll();
} else {
    $titulo = "Bienvenido {$nombreUsuario}";
    $mensajeInfo = 'Revisa tu historial y próximos medicamentos programados.';
    $acciones = [
        ['href' => '../historial/ver.php', 'titulo' => 'Ver mis dispensos', 'desc' => 'Consulta tu historial personal de dispensos.'],
    ];

    $checkColumn->execute(['public', 'programacion', 'id_paciente']);
    $hasIdPaciente = (bool) $checkColumn->fetchColumn();

    if ($hasIdPaciente) {
        $sql = "
            SELECT p.hora_dispenso, {$duracionSelect}, p.cantidad, m.nombre AS medicamento, m.dosis
            FROM programacion p
            JOIN medicamentos m ON p.id_medicamento = m.id_medicamento
            WHERE p.estado = 'activo' AND (p.id_paciente = ? OR p.id_usuario = ?)
            ORDER BY CASE WHEN p.hora_dispenso >= CURRENT_TIME THEN 0 ELSE 1 END, p.hora_dispenso ASC
            LIMIT 10
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$idUsuario, $idUsuario]);
    } else {
        $sql = "
            SELECT p.hora_dispenso, {$duracionSelect}, p.cantidad, m.nombre AS medicamento, m.dosis
            FROM programacion p
            JOIN medicamentos m ON p.id_medicamento = m.id_medicamento
            WHERE p.id_usuario = ? AND p.estado = 'activo'
            ORDER BY CASE WHEN p.hora_dispenso >= CURRENT_TIME THEN 0 ELSE 1 END, p.hora_dispenso ASC
            LIMIT 10
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$idUsuario]);
    }

    $proximos = $stmt->fetchAll();
}

$customLogoPath = '../assets/img/logo.png';
$logoDisponible = file_exists(__DIR__ . '/../assets/img/logo.png');

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titulo) ?></title>
    <link rel="stylesheet" href="../assets/css/general.css?v=<?= urlencode((string) @filemtime(__DIR__ . '/../assets/css/general.css')) ?>">
    <link rel="stylesheet" href="../assets/css/dashboard.css?v=<?= urlencode((string) @filemtime(__DIR__ . '/../assets/css/dashboard.css')) ?>">
</head>
<body>
<aside class="ui-tools" aria-label="Herramientas visuales">
    <div class="theme-switcher" role="group" aria-label="Selector de tema">
        <button type="button" class="theme-btn" id="modo-claro" data-theme="claro" aria-pressed="false">☀️ Claro</button>
        <button type="button" class="theme-btn" id="modo-oscuro" data-theme="oscuro" aria-pressed="false">🌙 Oscuro</button>
    </div>
    <button type="button" class="theme-btn dyslexia-btn" id="modo-dislexia" aria-pressed="false">🅰️ Modo dislexia</button>
    <span id="tema-actual" class="theme-label" aria-live="polite">Tema actual: automático</span>
</aside>
<div class="container">
    <div class="topbar card">
        <div class="topbar-main">
            <div class="brand-slot" title="Espacio reservado para logo">
                <?php if ($logoDisponible): ?>
                    <img src="<?= htmlspecialchars($customLogoPath) ?>" alt="Logo del sistema" class="brand-logo">
                <?php else: ?>
                    <div class="brand-placeholder">Logo</div>
                <?php endif; ?>
            </div>
            <div>
                <p class="role-chip"><?= htmlspecialchars(strtoupper($rol)) ?></p>
                <h1><?= htmlspecialchars($titulo) ?></h1>
            </div>
        </div>
        <div class="topbar-actions">
            <div class="theme-switcher" role="group" aria-label="Selector de tema">
                <button type="button" class="theme-btn" id="modo-claro" data-theme="claro" aria-pressed="false">☀️ Claro</button>
                <button type="button" class="theme-btn" id="modo-oscuro" data-theme="oscuro" aria-pressed="false">🌙 Oscuro</button>
            </div>
            <span id="tema-actual" class="theme-label" aria-live="polite">Tema actual: automático</span>
            <a class="btn btn-secondary" href="../auth/logout.php">Cerrar sesión</a>
        </div>
    </div>

    <section class="card">
        <h2>Información rápida</h2>
        <p><?= htmlspecialchars($mensajeInfo) ?></p>
        <p style="margin-top:8px;">
            Tienes <strong><?= count($acciones) ?></strong> accesos rápidos disponibles
            y <strong><?= count($proximos) ?></strong> medicamento(s) próximos en pantalla.
        </p>
    </section>

    <section class="card" style="margin-top: 16px;">
        <h2>Accesos rápidos</h2>
        <ul class="nav-links">
            <?php foreach ($acciones as $accion): ?>
                <li>
                    <a href="<?= htmlspecialchars($accion['href']) ?>">
                        <strong><?= htmlspecialchars($accion['titulo']) ?></strong>
                        <span><?= htmlspecialchars($accion['desc']) ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <?php if ($modoLegacy): ?>
        <div class="alert alert-error" style="margin-top:16px;">Modo legado activo: para ver próximos medicamentos por paciente, aplica la migración de BD en README.</div>
    <?php endif; ?>

    <?php if (!empty($proximos)): ?>
        <section class="card" style="margin-top: 16px;">
            <h2>Próximos medicamentos</h2>
            <table>
                <thead>
                <tr>
                    <?php if ($mostrarPacientes): ?><th>Paciente</th><?php endif; ?>
                    <th>Hora</th>
                    <th>Medicamento</th>
                    <th>Dosis</th>
                    <th>Cantidad</th>
                    <th>Duración (días)</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($proximos as $p): ?>
                    <tr>
                        <?php if ($mostrarPacientes): ?><td><?= htmlspecialchars((string) $p['paciente']) ?></td><?php endif; ?>
                        <td><?= htmlspecialchars((string) $p['hora_dispenso']) ?></td>
                        <td><?= htmlspecialchars((string) $p['medicamento']) ?></td>
                        <td><?= htmlspecialchars((string) ($p['dosis'] ?: 'No especificada')) ?></td>
                        <td><?= htmlspecialchars((string) $p['cantidad']) ?></td>
                        <td><?= htmlspecialchars((string) ($p['duracion_dias'] ?: 'No especificada')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    <?php endif; ?>
</div>
<script>
(() => {
    const body = document.body;
    const btnClaro = document.getElementById('modo-claro');
    const btnOscuro = document.getElementById('modo-oscuro');
    const btnDislexia = document.getElementById('modo-dislexia');
    const key = 'dispensador_tema';
    const keyDislexia = 'dispensador_dislexia';
    const almacenamiento = {
        get(storageKey = key) {
            try {
                return window.localStorage.getItem(storageKey);
            } catch (e) {
                return null;
            }
        },
        set(storageKey, valor) {
            try {
                window.localStorage.setItem(storageKey, valor);
            } catch (e) {
                // Ignorar entornos donde localStorage esté bloqueado.
            }
        }
    };

    function aplicarTema(modo) {
        body.classList.remove('theme-light', 'theme-dark');
        if (modo === 'oscuro') {
            body.classList.add('theme-dark');
            almacenamiento.set(key, 'oscuro');
        } else {
            body.classList.add('theme-light');
            almacenamiento.set(key, 'claro');
        }
        const temaActual = document.getElementById('tema-actual');
        if (temaActual) {
            temaActual.textContent = `Tema actual: ${modo}`;
        }
        if (btnClaro && btnOscuro) {
            btnClaro.classList.toggle('is-active', modo === 'claro');
            btnOscuro.classList.toggle('is-active', modo === 'oscuro');
            btnClaro.setAttribute('aria-pressed', modo === 'claro' ? 'true' : 'false');
            btnOscuro.setAttribute('aria-pressed', modo === 'oscuro' ? 'true' : 'false');
        }
    }

    const temaGuardado = almacenamiento.get(key);
    if (temaGuardado === 'oscuro' || temaGuardado === 'claro') {
        aplicarTema(temaGuardado);
    } else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
        aplicarTema('oscuro');
    }

    btnClaro?.addEventListener('click', () => aplicarTema('claro'));
    btnOscuro?.addEventListener('click', () => aplicarTema('oscuro'));

    function aplicarModoDislexia(activo) {
        body.classList.toggle('dyslexia-mode', activo);
        btnDislexia?.classList.toggle('is-active', activo);
        btnDislexia?.setAttribute('aria-pressed', activo ? 'true' : 'false');
        almacenamiento.set(keyDislexia, activo ? '1' : '0');
    }

    const dislexiaGuardado = almacenamiento.get(keyDislexia);
    if (dislexiaGuardado === '1') {
        aplicarModoDislexia(true);
    }

    btnDislexia?.addEventListener('click', () => {
        aplicarModoDislexia(!body.classList.contains('dyslexia-mode'));
    });
})();
</script>
</body>
</html>
