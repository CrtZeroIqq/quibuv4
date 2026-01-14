/**
 * Dashboard de Progreso del Grupo - Estadísticas Públicas
 * Archivo: stats-dashboard.js
 * Agregar a /pagar/assets/js/
 */

document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('grupoStatsContainer');
    
    // Si no existe el contenedor, salir
    if (!container) {
        console.log('Dashboard: Contenedor no encontrado');
        return;
    }
    
    // Obtener el ID del grupo desde el atributo data
    const grupoId = container.getAttribute('data-grupo-id');
    
    if (!grupoId) {
        console.error('Dashboard: No se pudo obtener el ID del grupo');
        showError('No se pudo obtener el ID del grupo');
        return;
    }
    
    console.log('Dashboard: Cargando estadísticas para grupo', grupoId);
    
    // Cargar estadísticas
    const apiUrl = `/api/estadisticas_publicas_grupo.php?idGrupo=${grupoId}`;
    console.log('Dashboard: URL de API:', apiUrl);
    
    fetch(apiUrl)
        .then(response => {
            console.log('Dashboard: Respuesta recibida', response.status);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Dashboard: Datos recibidos', data);
            if (data.success) {
                renderStats(data);
            } else {
                console.error('Dashboard: Error en datos', data.message);
                showError(data.message || 'No se pudieron cargar las estadísticas');
            }
        })
        .catch(error => {
            console.error('Dashboard: Error en fetch', error);
            showError('Error al conectar con el servidor: ' + error.message);
        });
    
    function renderStats(data) {
        const stats = data.estadisticasGenerales;
        const grupo = data.grupo;
        const periodo = data.periodo;
        
        // Calcular rango de fechas del curso
        let rangoFechas = '';
        if (periodo.fechaPrimeraCuota && periodo.fechaUltimaCuota) {
            const inicio = new Date(periodo.fechaPrimeraCuota + 'T00:00:00');
            const fin = new Date(periodo.fechaUltimaCuota + 'T00:00:00');
            const meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 
                          'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
            rangoFechas = `${meses[inicio.getMonth()]} ${inicio.getFullYear()} - ${meses[fin.getMonth()]} ${fin.getFullYear()}`;
        }
        
        const html = `
            ${rangoFechas ? `
            <div class="cuota-actual">
                <h3>Periodo del Curso</h3>
                <p class="cuota-mes">${rangoFechas}</p>
            </div>
            ` : ''}
            
            <div class="progress-section">
                <div class="progress-label">
                    <span>Personas Registradas</span>
                    <span><strong>${stats.pagadoresRegistrados}</strong> de <strong>${grupo.cantidadPersonas}</strong></span>
                </div>
                <div class="progress-bar-container">
                    <div class="progress-bar-fill" style="width: ${stats.porcentajeRegistro}%">
                        ${stats.porcentajeRegistro >= 15 ? stats.porcentajeRegistro + '%' : ''}
                    </div>
                </div>
            </div>
            
            <div class="progress-section">
                <div class="progress-label">
                    <span>Total Recaudado</span>
                    <span><strong>${formatCLP(stats.totalRecaudado)}</strong> de <strong>${formatCLP(stats.metaTotalCurso)}</strong></span>
                </div>
                <div class="progress-bar-container">
                    <div class="progress-bar-fill" style="width: ${stats.porcentajeRecaudacion}%">
                        ${stats.porcentajeRecaudacion >= 15 ? stats.porcentajeRecaudacion + '%' : ''}
                    </div>
                </div>
            </div>
            
            <div class="stats-footer">
                💡 Ingresa tu RUT para ver tu estado personal de pago
            </div>
        `;
        
        container.innerHTML = html;
    }
    
    function showError(message) {
        container.innerHTML = `
            <div class="stats-error">
                <p>⚠️ ${message}</p>
            </div>
        `;
    }
    
    function formatCLP(amount) {
        return '$' + amount.toLocaleString('es-CL');
    }
});