<?php
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header('Location: ../index.php');
    exit;
}

$rol = $_SESSION['rol'] ?? 'paciente';
$dashboard = '../dashboard/paciente.php';
if ($rol === 'admin') {
    $dashboard = '../dashboard/admin.php';
} elseif ($rol === 'cuidador') {
    $dashboard = '../dashboard/cuidador.php';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agregar medicamento</title>
    <link rel="stylesheet" href="../assets/css/general.css?v=<?= urlencode((string) @filemtime(__DIR__ . '/../assets/css/general.css')) ?>">
    <link rel="stylesheet" href="../assets/css/forms.css?v=<?= urlencode((string) @filemtime(__DIR__ . '/../assets/css/forms.css')) ?>">
</head>
<body>
<div class="container">
    <div class="card">
        <h1>Agregar medicamento</h1>
        <p>
            <a href="listar.php">← Ver listado</a>
            &nbsp;|&nbsp;
            <a href="<?= htmlspecialchars($dashboard, ENT_QUOTES, 'UTF-8') ?>">Volver al dashboard</a>
        </p>

        <div class="alert alert-success">
            Catálogo conectado (Ecuador): se muestran solo medicamentos comestibles (pastillas/cápsulas) y se autocompleta el formulario.
        </div>
        <?php if (($_GET['error'] ?? '') === 'tipo_no_comestible'): ?>
            <div class="alert alert-error">
                Solo puedes guardar medicamentos comestibles para el dispensador (pastillas/cápsulas).
            </div>
        <?php endif; ?>

        <label for="medicamento_api">Medicamento</label>
        <select id="medicamento_api" style="margin-bottom: 12px;">
            <option value="">Cargando medicamentos...</option>
        </select>

        <form action="guardar.php" method="POST" id="form_medicamento">
            <label for="nombre">Nombre</label>
            <input id="nombre" name="nombre" placeholder="Nombre" required>

            <label for="tipo">Tipo</label>
            <input id="tipo" name="tipo" placeholder="Tipo" required>

            <label for="dosis">Dosis</label>
            <input id="dosis" name="dosis" placeholder="Dosis" required>

            <label for="cantidad_total">Cantidad total</label>
            <input id="cantidad_total" name="cantidad_total" type="number" min="1" required>

            <label for="fecha_vencimiento">Fecha de vencimiento</label>
            <input id="fecha_vencimiento" name="fecha_vencimiento" type="date" required>

            <button type="submit">Guardar medicamento</button>
        </form>
    </div>
</div>

<script>
const selectMedicamento = document.getElementById('medicamento_api');
const nombreInput = document.getElementById('nombre');
const tipoInput = document.getElementById('tipo');
const dosisInput = document.getElementById('dosis');
const formMedicamento = document.getElementById('form_medicamento');

function esTipoComestible(tipo = '') {
    const t = String(tipo).toLowerCase().trim();
    if (!t) return false;

    const permitidos = [
        'tablet', 'capsule', 'caplet', 'pill', 'comprimido', 'cápsula',
        'capsula', 'gragea', 'pastilla', 'chewable', 'lozenge', 'troche', 'oral'
    ];

    return permitidos.some((p) => t.includes(p));
}

async function cargarMedicamentos() {
    try {
        const response = await fetch('../api/medicamentos_externos.php');
        if (!response.ok) throw new Error();

        const data = await response.json();
        const meds = data.medicamentos || [];

        selectMedicamento.innerHTML = '<option value="">Selecciona un medicamento</option>';

        meds.forEach((med, index) => {
            const option = document.createElement('option');
            option.value = index;
            option.textContent = `${med.nombre} - ${med.tipo} - ${med.dosis}`;
            option.dataset.nombre = med.nombre;
            option.dataset.tipo = med.tipo;
            option.dataset.dosis = med.dosis;
            selectMedicamento.appendChild(option);
        });
    } catch (e) {
        selectMedicamento.innerHTML = '<option value="">No se pudo cargar la API</option>';
    }
}

selectMedicamento.addEventListener('change', (e) => {
    const option = e.target.selectedOptions[0];
    if (!option || !option.dataset.nombre) return;

    nombreInput.value = option.dataset.nombre;
    tipoInput.value = option.dataset.tipo;
    dosisInput.value = option.dataset.dosis;
});

formMedicamento.addEventListener('submit', (e) => {
    if (!esTipoComestible(tipoInput.value)) {
        e.preventDefault();
        alert('Solo se permiten medicamentos comestibles (pastillas/cápsulas).');
    }
});

cargarMedicamentos();
</script>
</body>
</html>
