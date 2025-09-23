// Variables globales
let estudiantes = [];
let attendanceRecords = [];
let isUpdating = false;
let searchTimeout;

// Inicialización
document.addEventListener('DOMContentLoaded', function() {
    loadData();
    setupEventListeners();
    updateCurrentDate();
    renderTabla();
    setupSearch();
});

// Cargar datos
function loadData() {
    // Cargar desde localStorage o usar datos por defecto
    const savedStudents = localStorage.getItem('students');
    const savedAttendance = localStorage.getItem('attendanceRecords');

    if (savedStudents) {
        estudiantes = JSON.parse(savedStudents);
    } else {
        // Datos por defecto
        estudiantes = [
            { codigo: "123", nombre: "Juan Pérez", grado: "10-1", asistencia: false, foto: "https://images.pexels.com/photos/1043471/pexels-photo-1043471.jpeg?auto=compress&cs=tinysrgb&w=50&h=50&fit=crop" },
            { codigo: "124", nombre: "María López", grado: "9-2", asistencia: false, foto: "https://images.pexels.com/photos/1239291/pexels-photo-1239291.jpeg?auto=compress&cs=tinysrgb&w=50&h=50&fit=crop" },
            { codigo: "125", nombre: "Carlos Ramírez", grado: "11-1", asistencia: false, foto: "https://images.pexels.com/photos/1043471/pexels-photo-1043471.jpeg?auto=compress&cs=tinysrgb&w=50&h=50&fit=crop" }
        ];
    }

    if (savedAttendance) {
        attendanceRecords = JSON.parse(savedAttendance);
    } else {
        attendanceRecords = [];
    }
}

// Configurar event listeners
function setupEventListeners() {
    const input = document.getElementById('codigoInput');
    if (input) {
        input.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                hideSearchSuggestions();
                registrarAsistencia();
            }
        });
        
        setTimeout(() => {
            input.focus();
        }, 500);
    }
}

// Actualizar fecha actual
function updateCurrentDate() {
    const dateElement = document.getElementById('currentDate');
    if (dateElement) {
        const today = new Date();
        const options = { 
            weekday: 'long', 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        };
        dateElement.textContent = today.toLocaleDateString('es-ES', options);
    }
}

// Registrar asistencia
function registrarAsistencia() {
    const input = document.getElementById('codigoInput');
    const codigo = input.value.trim();
    
    if (!codigo) {
        showToast('⚠️ Por favor ingresa un código', 'warning');
        input.focus();
        
        // Efecto de shake
        input.style.animation = 'shake 0.5s ease-in-out';
        setTimeout(() => {
            input.style.animation = '';
        }, 500);
        return;
    }
    
    // Mostrar loading
    const btn = document.querySelector('button[onclick="registrarAsistencia()"]');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Buscando...';
    btn.disabled = true;
    
    // Simular búsqueda
    setTimeout(() => {
        const estudiante = estudiantes.find(e => e.codigo === codigo);

        if (estudiante) {
            const today = new Date().toISOString().split('T')[0];
            const alreadyRegistered = attendanceRecords.find(r => 
                r.studentCode === codigo && r.fecha === today
            );
            
            if (!alreadyRegistered) {
                // Registrar asistencia
                const newRecord = {
                    id: Date.now(),
                    studentCode: codigo,
                    studentName: estudiante.nombre,
                    studentGrade: estudiante.grado,
                    studentPhoto: estudiante.foto,
                    fecha: today,
                    hora: new Date().toLocaleTimeString('es-ES')
                };
                
                attendanceRecords.push(newRecord);
                localStorage.setItem('attendanceRecords', JSON.stringify(attendanceRecords));
                
                // Actualizar preview del estudiante
                updateStudentPreview(estudiante);
                
                showToast(
                    `🎉 ¡Bienvenido ${estudiante.nombre}!`, 
                    'success'
                );
                
                renderTabla();
                actualizarContador();
                
            } else {
                showToast(
                    `⚠️ ${estudiante.nombre} ya está registrado hoy`, 
                    'warning'
                );
            }
        } else {
            showToast(
                '❌ Código no encontrado', 
                'error'
            );
        }
        
        // Restaurar botón
        btn.innerHTML = originalText;
        btn.disabled = false;
        
        // Limpiar input
        setTimeout(() => {
            input.value = '';
            input.focus();
        }, 1000);
        
    }, 800);
}

// Actualizar preview del estudiante
function updateStudentPreview(estudiante) {
    const photoImg = document.getElementById('fotoEstudiante');
    const nameElement = document.getElementById('studentName');
    const gradeElement = document.getElementById('studentGrade');
    const codeElement = document.getElementById('studentCode');
    
    if (photoImg) {
        photoImg.style.opacity = '0';
        setTimeout(() => {
            photoImg.src = estudiante.foto;
            photoImg.style.opacity = '1';
        }, 200);
    }
    
    if (nameElement) nameElement.textContent = estudiante.nombre;
    if (gradeElement) gradeElement.textContent = estudiante.grado;
    if (codeElement) codeElement.textContent = estudiante.codigo;
}

// Renderizar tabla
function renderTabla() {
    if (isUpdating) return;
    isUpdating = true;
    
    const cuerpoTabla = document.getElementById('cuerpoTabla');
    const emptyState = document.getElementById('emptyState');
    const tabla = document.getElementById('tablaAsistencia');
    
    if (!cuerpoTabla || !emptyState || !tabla) {
        isUpdating = false;
        return;
    }
    
    const today = new Date().toISOString().split('T')[0];
    const todayRecords = attendanceRecords.filter(record => record.fecha === today);
    
    if (todayRecords.length === 0) {
        tabla.style.display = 'none';
        emptyState.style.display = 'block';
    } else {
        emptyState.style.display = 'none';
        tabla.style.display = 'table';
        
        cuerpoTabla.innerHTML = '';
        
        todayRecords.forEach((record, index) => {
            setTimeout(() => {
                const fila = document.createElement('tr');
                fila.style.opacity = '0';
                fila.style.transform = 'translateX(-20px)';
                
                fila.innerHTML = `
                    <td><strong class="codigo-highlight">${record.studentCode}</strong></td>
                    <td class="nombre-cell">${record.studentName}</td>
                    <td><span class="grado-badge">${record.studentGrade}</span></td>
                    <td>${record.hora}</td>
                    <td><span class="status-badge status-present">Presente</span></td>
                    <td>
                        <button onclick="eliminarRegistro('${record.id}')" class="btnEliminar" title="Eliminar registro">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                `;
                
                cuerpoTabla.appendChild(fila);
                
                setTimeout(() => {
                    fila.style.opacity = '1';
                    fila.style.transform = 'translateX(0)';
                }, 50);
                
            }, index * 100);
        });
    }
    
    isUpdating = false;
}

// Eliminar registro
function eliminarRegistro(recordId) {
    const record = attendanceRecords.find(r => r.id == recordId);
    if (record && confirm(`¿Estás seguro de eliminar el registro de ${record.studentName}?`)) {
        attendanceRecords = attendanceRecords.filter(r => r.id != recordId);
        localStorage.setItem('attendanceRecords', JSON.stringify(attendanceRecords));
        
        renderTabla();
        actualizarContador();
        
        showToast(`🗑️ Registro eliminado correctamente`, 'info');
    }
}

// Actualizar contador
function actualizarContador() {
    const contador = document.getElementById('contadorAsistentes');
    if (contador) {
        const today = new Date().toISOString().split('T')[0];
        const todayCount = attendanceRecords.filter(r => r.fecha === today).length;
        contador.textContent = `${todayCount} Registrados`;
    }
}

// Guardar cambios
function guardarCambios() {
    const today = new Date().toISOString().split('T')[0];
    const todayRecords = attendanceRecords.filter(r => r.fecha === today);
    
    if (todayRecords.length === 0) {
        showToast('⚠️ No hay registros para guardar', 'warning');
        return;
    }
    
    const btn = document.querySelector('.save-btn');
    if (!btn) return;
    
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
    
    // Simular guardado
    setTimeout(() => {
        // Aquí se haría la petición al backend
        fetch('backend/procesar.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'save_attendance',
                records: todayRecords
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('💾 Datos guardados correctamente', 'success');
            } else {
                showToast('❌ Error al guardar los datos', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('💾 Datos guardados localmente', 'success');
        });
        
        btn.innerHTML = originalText;
        btn.disabled = false;
    }, 1500);
}

// Exportar asistencia
function exportAttendance() {
    const today = new Date().toISOString().split('T')[0];
    const todayRecords = attendanceRecords.filter(r => r.fecha === today);
    
    if (todayRecords.length === 0) {
        showToast('⚠️ No hay registros para exportar', 'warning');
        return;
    }
    
    // Crear CSV
    const headers = ['Código', 'Nombre', 'Grado', 'Fecha', 'Hora'];
    const csvContent = [
        headers.join(','),
        ...todayRecords.map(record => [
            record.studentCode,
            record.studentName,
            record.studentGrade,
            record.fecha,
            record.hora
        ].join(','))
    ].join('\n');
    
    // Descargar archivo
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `asistencia_${today}.csv`;
    a.click();
    window.URL.revokeObjectURL(url);
    
    showToast('📄 Archivo exportado correctamente', 'success');
}

// Filtrar por grado
function filterByGrade(grade) {
    // Implementar filtro por grado
    renderTabla();
}

// Búsqueda en tiempo real
function setupSearch() {
    const input = document.getElementById('codigoInput');
    if (!input) return;
    
    input.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const query = this.value.trim();
        
        if (query.length >= 2) {
            searchTimeout = setTimeout(() => {
                const matches = estudiantes.filter(e => 
                    e.codigo.includes(query) || 
                    e.nombre.toLowerCase().includes(query.toLowerCase())
                );
                
                if (matches.length > 0 && matches.length < 5) {
                    showSearchSuggestions(matches, input);
                } else {
                    hideSearchSuggestions();
                }
            }, 300);
        } else {
            hideSearchSuggestions();
        }
    });
}

function showSearchSuggestions(matches, input) {
    hideSearchSuggestions();
    
    const suggestions = document.createElement('div');
    suggestions.id = 'searchSuggestions';
    suggestions.style.cssText = `
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: white;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        z-index: 1000;
        max-height: 200px;
        overflow-y: auto;
    `;
    
    matches.forEach(match => {
        const item = document.createElement('div');
        item.style.cssText = `
            padding: 1rem;
            cursor: pointer;
            border-bottom: 1px solid #f3f4f6;
            transition: background 0.2s ease;
        `;
        
        item.innerHTML = `
            <strong>${match.codigo}</strong> - ${match.nombre} 
            <span style="color: #667eea; font-size: 0.9rem;">(${match.grado})</span>
        `;
        
        item.addEventListener('mouseenter', () => {
            item.style.background = 'rgba(102, 126, 234, 0.1)';
        });
        
        item.addEventListener('mouseleave', () => {
            item.style.background = 'transparent';
        });
        
        item.addEventListener('click', () => {
            input.value = match.codigo;
            hideSearchSuggestions();
            registrarAsistencia();
        });
        
        suggestions.appendChild(item);
    });
    
    const container = input.closest('.input-icon');
    container.style.position = 'relative';
    container.appendChild(suggestions);
}

function hideSearchSuggestions() {
    const suggestions = document.getElementById('searchSuggestions');
    if (suggestions) {
        suggestions.remove();
    }
}

// Función para mostrar notificaciones
function showToast(message, type = 'info') {
    const container = document.getElementById('toastContainer');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    
    const icons = {
        success: '✅',
        error: '❌',
        warning: '⚠️',
        info: 'ℹ️'
    };

    toast.innerHTML = `
        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <span style="font-size: 1.25rem;">${icons[type]}</span>
            <span>${message}</span>
        </div>
    `;

    container.appendChild(toast);

    setTimeout(() => {
        if (toast.parentNode) {
            toast.remove();
        }
    }, 4000);
}