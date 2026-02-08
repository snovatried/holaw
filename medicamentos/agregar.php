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
</head>
<body>
    <h1>Agregar medicamento</h1>

    <label for="medicamento_api">Medicamento (API externa, sin jarabes)</label>
    <select id="medicamento_api">
        <option value="">Cargando medicamentos...</option>
    </select>

    <form action="guardar.php" method="POST" style="margin-top: 1rem;">
        <input id="nombre" name="nombre" placeholder="Nombre" required>
        <input id="tipo" name="tipo" placeholder="Tipo" required>
        <input id="dosis" name="dosis" placeholder="Dosis" required>
        <input name="cantidad_total" type="number" min="1" required>
        <input name="fecha_vencimiento" type="date" required>
        <button>Guardar</button>
    </form>

    <script>
        const selectMedicamento = document.getElementById('medicamento_api');
        const nombreInput = document.getElementById('nombre');
        const tipoInput = document.getElementById('tipo');
        const dosisInput = document.getElementById('dosis');

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

        cargarMedicamentos();
    </script>
</body>
</html>
