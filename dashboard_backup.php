<?php
// Configuración básica
$api_url = 'api.php';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Totem Logs</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
            padding-top: 20px;
        }
        .card {
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .table-responsive {
            max-height: 500px;
            overflow-y: auto;
        }
        .badge-confirmada {
            background-color: #28a745;
        }
        .badge-pendiente {
            background-color: #ffc107;
            color: #212529;
        }
        .badge-cancelada {
            background-color: #dc3545;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="text-center mb-4">Visualización Totem Logs</h1>
        
        <div class="row mb-3">
            <div class="col-md-6">
                <div class="input-group mb-2">
                    <input type="number" class="form-control" id="searchId" placeholder="Buscar por ID">
                    <button class="btn btn-primary" id="searchBtn">
                        <i class="bi bi-search"></i> Buscar ID
                    </button>
                </div>
                <div class="input-group">
                    <input type="text" class="form-control" id="searchRut" placeholder="Buscar por RUT (ej: 12345678-9)">
                    <button class="btn btn-primary" id="searchRutBtn">
                        <i class="bi bi-search"></i> Buscar RUT
                    </button>
                </div>
            </div>
            <div class="col-md-6 text-end">
                <button class="btn btn-success" id="refreshBtn">
                    <i class="bi bi-arrow-clockwise"></i> Actualizar
                </button>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Registros Totem Logs</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>RUT</th>
                                <th>Origen</th>
                                <th>Destino</th>
                                <th>Fecha Viaje</th>
                                <th>Hora Viaje</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="recordsBody">
                            <!-- Datos se cargarán con JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Modal para ver detalles -->
        <div class="modal fade" id="detailsModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Detalles del Registro</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" id="modalBody">
                        <!-- Contenido se cargará dinámicamente -->
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const apiUrl = '<?php echo $api_url; ?>';
        const detailsModal = new bootstrap.Modal(document.getElementById('detailsModal'));
        
        // Cargar registros al iniciar
        document.addEventListener('DOMContentLoaded', function() {
            loadAllRecords();
            
            document.getElementById('searchRutBtn').addEventListener('click', searchByRut);
            document.getElementById('searchBtn').addEventListener('click', searchRecord);
            document.getElementById('refreshBtn').addEventListener('click', loadAllRecords);
        });    
        

       // Función reutilizable para mostrar registros
        function renderRecords(data, rut = null) {
            const tbody = document.getElementById('recordsBody');
            tbody.innerHTML = '';

            if (data.success && data.data.length > 0) {
                data.data.forEach(record => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${record.id}</td>
                        <td>${record.rut}</td>
                        <td>${record.origen}</td>
                        <td>${record.destino}</td>
                        <td>${record.fecha_viaje}</td>
                        <td>${record.hora_viaje}</td>
                        <td>${record.numero_boleto}</td>
                        <td><span class="badge ${getStatusBadgeClass(record.estado_transaccion)}">${record.estado_transaccion}</span></td>
                        <td>
                            <button class="btn btn-sm btn-info" onclick="viewRecordDetails(${record.id})">
                                <i class="bi bi-eye"></i> Ver
                            </button>
                        </td>
                    `;
                    tbody.appendChild(row);
                });

                if (rut) {
                    showAlert(`Se encontraron ${data.data.length} registros para el RUT ${rut}`, 'success');
                }
            } else {
                tbody.innerHTML = `<tr><td colspan="8" class="text-center">${rut ? 'No se encontraron registros para este RUT' : 'No hay registros disponibles'}</td></tr>`;
                showAlert(rut ? 'No se encontraron registros para este RUT' : 'No hay registros disponibles', 'info');
            }
        }

        // Función genérica para obtener registros
        function fetchRecords(endpoint, rut = null) {
            const tbody = document.getElementById('recordsBody');
            tbody.innerHTML = '<tr><td colspan="8" class="text-center">Buscando...</td></tr>';

            fetch(endpoint)
                .then(response => response.json())
                .then(data => renderRecords(data, rut))
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('Error al buscar registros', 'danger');
                    tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger">Error al buscar registros</td></tr>';
                });
        }

        // Función para buscar por RUT
        function searchByRut() {
            const rut = document.getElementById('searchRut').value.trim();

            if (!rut) {
                showAlert('Por favor ingrese un RUT válido', 'warning');
                return;
            }

            fetchRecords(`${apiUrl}?rut=${encodeURIComponent(rut)}`, rut);
        }

        // Función para cargar todos los registros
        function loadAllRecords() {
            fetchRecords(apiUrl);
        }

        // Función para buscar un registro por ID
        function searchRecord() {
            const id = document.getElementById('searchId').value;
            if (!id || id <= 0) {
                showAlert('Por favor ingrese un ID válido', 'warning');
                return;
            }
            
            fetch(`${apiUrl}?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        viewRecordDetails(id);
                    } else {
                        showAlert(data.error || 'Registro no encontrado', 'danger');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('Error al buscar el registro', 'danger');
                });
        }        
        
        // Función para ver detalles de un registro
        function viewRecordDetails(id) {
            fetch(`${apiUrl}?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const record = data.data;
                        document.getElementById('modalBody').innerHTML = `
                            <div class="container-fluid">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h5 class="mb-3">Información del Pasajero</h5>
                                        <div class="mb-3">
                                            <p><strong>ID:</strong> ${record.id}</p>
                                            <p><strong>RUT:</strong> ${record.rut}</p>
                                            <p><strong>Número de Tótem:</strong> ${record.numTotem || 'N/A'}</p>
                                        </div>
                                        
                                        <h5 class="mb-3">Información del Viaje</h5>
                                        <div class="mb-3">
                                            <p><strong>Origen:</strong> ${record.origen}</p>
                                            <p><strong>Destino:</strong> ${record.destino}</p>
                                            <p><strong>Fecha Viaje:</strong> ${record.fecha_viaje}</p>
                                            <p><strong>Hora Viaje:</strong> ${record.hora_viaje}</p>
                                            <p><strong>Asiento:</strong> ${record.asiento}</p>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <h5 class="mb-3">Información de la Reserva</h5>
                                        <div class="mb-3">
                                            <p><strong>Código Reserva:</strong> ${record.codigo_reserva || 'N/A'}</p>
                                            <p><strong>Estado Boleto:</strong> <span class="badge ${getStatusBadgeClass(record.estado_boleto)}">${record.estado_boleto}</span></p>                                            
                                        </div>
                                        
                                        <h5 class="mb-3">Información de Transacción</h5>
                                        <div class="mb-3">
                                            <p><strong>Código Transacción:</strong> ${record.codigo_transaccion || 'N/A'}</p>
                                            <p><strong>Estado Transacción:</strong> <span class="badge ${getStatusBadgeClass(record.estado_transaccion)}">${record.estado_transaccion}</span></p>
                                            <p><strong>Número Transacción:</strong> ${record.numero_transaccion || 'N/A'}</p>
                                            <p><strong>Número de boleto:</strong> ${record.numero_boleto || 'N/A'}</p>
                                            <p><strong>Fecha Transacción:</strong> ${record.fecha_transaccion || 'N/A'}</p>
                                            <p><strong>Hora Transacción:</strong> ${record.hora_transaccion || 'N/A'}</p>
                                            <p><strong>Total Transacción:</strong> ${record.total_transaccion ? '$' + record.total_transaccion : 'N/A'}</p>
                                        </div>
                                    </div>
                                </div>
                                
                                <hr>
                                
                                <div class="row">
                                    <div class="col-12">
                                        <p><strong>Fecha de Creación:</strong> ${new Date(record.created_at).toLocaleString()}</p>
                                        ${record.updated_at ? `<p><strong>Última Actualización:</strong> ${new Date(record.updated_at).toLocaleString()}</p>` : ''}
                                    </div>
                                </div>
                            </div>
                        `;
                        detailsModal.show();
                    } else {
                        showAlert(data.error || 'Registro no encontrado', 'danger');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('Error al cargar los detalles', 'danger');
                });
        }
        
        
        // Función auxiliar para obtener clase CSS según estado
        function getStatusBadgeClass(status) {
            if (!status) return 'bg-secondary';
            
            status = status.toLowerCase();
            switch(status) {
                case 'pago realizado':
                case 'confirmada':
                case 'aprobada':
                case 'success':
                    return 'badge-confirmada'; // Verde
                case 'pendiente':
                case 'pending':
                    return 'badge-pendiente'; // Amarillo
                case 'pago fallido':
                case 'cancelada':
                case 'rechazada':
                case 'failed':
                    return 'badge-cancelada'; // Rojo
                default: 
                    return 'bg-secondary'; // Gris para estados desconocidos
            }
        }
        
        // Función para mostrar alertas
        function showAlert(message, type) {
            const alert = document.createElement('div');
            alert.className = `alert alert-${type} alert-dismissible fade show`;
            alert.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;
            
            const container = document.querySelector('.container');
            container.prepend(alert);
            
            setTimeout(() => {
                alert.remove();
            }, 5000);
        }
    </script>
</body>
</html>