# 📋 Guía de Gestión de Logs - Sistema GPS MININTER

## 🚨 Problema Resuelto

### Síntoma
- Error: `Allowed memory size of 134217728 bytes exhausted` al acceder a `/admin/log-viewer`
- Archivos de log extremadamente grandes (50MB+) causando agotamiento de memoria

### Causa
- Los archivos de log crecían sin límite
- El visor intentaba cargar archivos completos en memoria
- Falta de limpieza automática de logs

### Solución Implementada
✅ **Botones de limpieza** integrados en el visor de logs  
✅ **Lectura eficiente** de archivos grandes (solo últimas 1000 líneas)  
✅ **Comandos de limpieza** manual disponibles  
✅ **Notificaciones** informativas sobre archivos grandes  

---

## 🔧 Nuevas Funcionalidades del Visor de Logs

### Botones Agregados

#### 1. **Limpiar Logs Actuales** 🗑️
- **Función**: Limpia logs del día seleccionado y canal especificado
- **Icono**: 🗑️ (heroicon-o-trash)
- **Color**: Advertencia (amarillo)
- **Confirmación**: Sí, requiere confirmación con detalles específicos

#### 2. **Limpiar Todos los Logs** 🔥
- **Función**: Limpia TODOS los archivos de log del sistema
- **Icono**: 🔥 (heroicon-o-fire)
- **Color**: Peligro (rojo)
- **Confirmación**: Sí, con advertencia fuerte

### Mejoras de Rendimiento

#### Lectura Inteligente de Archivos
- **Límite de tamaño**: 10MB por archivo
- **Archivos pequeños**: Se cargan completamente
- **Archivos grandes**: Solo se leen las últimas 1000 líneas
- **Notificación**: Aviso automático cuando se detecta un archivo grande

---

## 🖥️ Comandos de Terminal

### 1. Limpieza Manual de Logs

#### Comando Principal
```bash
# Comando disponible
php artisan gps:log-cleanup

# Opciones disponibles
--days=30          # Días de retención (default: 30)
--dry-run          # Ver qué se eliminaría sin borrar
--force            # Forzar sin confirmación
```

#### Ejemplos de Uso
```bash
# Ver qué logs se eliminarían (7 días de retención)
php artisan gps:log-cleanup --days=7 --dry-run

# Limpiar logs antiguos de 7 días
php artisan gps:log-cleanup --days=7 --force

# Limpiar logs antiguos (30 días default)
php artisan gps:log-cleanup --force
```

### 2. Limpieza Inmediata (Truncar)

#### Truncar Archivos Específicos
```bash
# Truncar logs de hoy
truncate -s 0 storage/logs/gps/errors-$(date +%Y-%m-%d).log
truncate -s 0 storage/logs/gps/gps-$(date +%Y-%m-%d).log
truncate -s 0 storage/logs/gps/transmissions-$(date +%Y-%m-%d).log
truncate -s 0 storage/logs/gps/system-$(date +%Y-%m-%d).log

# Truncar log principal de Laravel
truncate -s 0 storage/logs/laravel.log
```

#### Truncar Todos los Logs
```bash
# Truncar todos los logs GPS
find storage/logs/gps/ -name "*.log" -exec truncate -s 0 {} \;

# Truncar todos los logs del sistema
find storage/logs/ -name "*.log" -exec truncate -s 0 {} \;
```

### 3. Monitoreo de Tamaño de Logs

#### Verificar Tamaños
```bash
# Ver tamaño de todos los logs
find storage/logs -type f -name "*.log" -exec ls -lh {} \;

# Ver solo logs grandes (>10MB)
find storage/logs -type f -name "*.log" -size +10M -exec ls -lh {} \;

# Resumen por directorio
du -h storage/logs/
```

#### Monitoreo Continuo
```bash
# Monitorear crecimiento de logs en tiempo real
watch -n 5 'find storage/logs -name "*.log" -exec ls -lh {} \;'
```

---

## 🚀 Uso del Visor de Logs

### Acceso
```
URL: /admin/log-viewer
Requiere: Autenticación de admin
```

### Funcionalidades

#### Filtros Disponibles
- **Canal**: Todos, GPS, Transmisiones, Sistema, Errores
- **Nivel**: Todos, Debug, Info, Warning, Error
- **Fecha**: Selector de fecha específica
- **Búsqueda**: Búsqueda de texto en mensajes

#### Acciones Disponibles
1. **Actualizar**: Recargar logs actuales
2. **Descargar**: Descargar logs filtrados
3. **Limpiar Logs Actuales**: Limpiar logs del día/canal seleccionado
4. **Limpiar Todos los Logs**: Limpieza completa del sistema

### Límites de Rendimiento
- **Máximo entradas mostradas**: 500 (para rendimiento)
- **Archivos grandes**: Solo últimas 1000 líneas
- **Auto-refresh**: Cada 30 segundos
- **Límite de tamaño**: 10MB por archivo antes de lectura parcial

---

## 📊 Configuración de Logs

### Canales Configurados
```php
// config/logging.php
'gps' => [
    'driver' => 'daily',
    'path' => storage_path('logs/gps/gps.log'),
    'days' => env('GPS_LOG_RETENTION_DAYS', 30),
],
'transmissions' => [
    'driver' => 'daily', 
    'path' => storage_path('logs/gps/transmissions.log'),
    'days' => env('GPS_LOG_RETENTION_DAYS', 30),
],
'system' => [
    'driver' => 'daily',
    'path' => storage_path('logs/gps/system.log'), 
    'days' => env('GPS_LOG_RETENTION_DAYS', 30),
],
```

### Variables de Entorno
```bash
# .env
GPS_LOG_RETENTION_DAYS=30  # Días de retención de logs
LOG_LEVEL=debug           # Nivel de logging
```

---

## 🔄 Automatización

### Limpieza Automática
```bash
# Agregar a crontab para limpieza diaria
0 2 * * * /usr/bin/php /path/to/mininter/artisan gps:log-cleanup --days=7 --force
```

### Monitoreo de Espacio
```bash
# Script de monitoreo (crear en deployment/monitor-logs.sh)
#!/bin/bash
LOG_DIR="/var/www/mininter/storage/logs"
MAX_SIZE="100M"

# Verificar tamaño total
TOTAL_SIZE=$(du -s $LOG_DIR | cut -f1)
if [ $TOTAL_SIZE -gt 104857600 ]; then # 100MB en bytes
    echo "ALERT: Log directory exceeds 100MB"
    # Ejecutar limpieza automática
    /usr/bin/php /var/www/mininter/artisan gps:log-cleanup --days=3 --force
fi
```

---

## 🛠️ Solución de Problemas

### Error de Memoria
```bash
# Síntoma: "Allowed memory size exhausted"
# Solución inmediata:
truncate -s 0 storage/logs/gps/*.log
truncate -s 0 storage/logs/laravel.log
```

### Archivos de Log Gigantes
```bash
# Encontrar archivos grandes
find storage/logs -size +50M -name "*.log"

# Truncar archivos específicos
truncate -s 0 [archivo_grande.log]
```

### Permisos de Archivos
```bash
# Asegurar permisos correctos
chmod 664 storage/logs/gps/*.log
chown www-data:www-data storage/logs/gps/*.log
```

---

## 📋 Checklist de Mantenimiento

### Diario
- [ ] Revisar tamaño de logs en visor
- [ ] Verificar errores críticos
- [ ] Limpiar logs si exceden 50MB

### Semanal  
- [ ] Ejecutar `php artisan gps:log-cleanup --days=7`
- [ ] Revisar patrones de errores
- [ ] Verificar espacio en disco

### Mensual
- [ ] Analizar tendencias de logs
- [ ] Ajustar retención según necesidad
- [ ] Actualizar scripts de monitoreo

---

## 🎯 Próximos Pasos

### Mejoras Planeadas
1. **Rotación automática** de logs por tamaño
2. **Compresión** de logs antiguos
3. **Alertas** por email cuando logs exceden límites
4. **Dashboard** de métricas de logs
5. **Integración** con herramientas de monitoreo externas

### Configuración Recomendada
```bash
# .env recomendado para producción
GPS_LOG_RETENTION_DAYS=7
LOG_LEVEL=info
LOG_DAILY_DAYS=7
```

---

*Documento actualizado: $(date '+%d/%m/%Y %H:%M:%S')*  
*Autor: Sistema GPS MININTER*  
*Versión: 1.0* 