# 🚀 MININTER GPS - Configuración de Servicios en Producción

Esta guía te ayudará a configurar los servicios del sistema GPS MININTER para que funcionen automáticamente en tu servidor de producción.

## 📋 Requisitos Previos

- **Ubuntu/Debian** (con systemd)
- **PHP 8.1+** instalado
- **Composer** instalado
- **Acceso root** al servidor
- **Proyecto clonado** en `/var/www/mininter` (o ajustar rutas)

## 🔧 Configuración Automática (Recomendado)

### 1. Subir archivos al servidor

```bash
# Copiar archivos de deployment al servidor
scp -r deployment/ usuario@tu-servidor:/var/www/mininter/
```

### 2. Ejecutar instalación automática

```bash
# Conectar al servidor
ssh usuario@tu-servidor

# Ir al directorio del proyecto
cd /var/www/mininter/deployment

# Dar permisos de ejecución
chmod +x install-services.sh

# Ejecutar instalación (como root)
sudo ./install-services.sh
```

## ⚙️ Configuración Manual

### 1. Copiar archivos de servicio

```bash
# Copiar archivos de servicio a systemd
sudo cp mininter-queue.service /etc/systemd/system/
sudo cp mininter-scheduler.service /etc/systemd/system/
```

### 2. Ajustar rutas en los archivos de servicio

Editar los archivos y cambiar `/var/www/mininter` por tu ruta real:

```bash
sudo nano /etc/systemd/system/mininter-queue.service
sudo nano /etc/systemd/system/mininter-scheduler.service
```

### 3. Configurar y habilitar servicios

```bash
# Recargar systemd
sudo systemctl daemon-reload

# Habilitar servicios (inicio automático)
sudo systemctl enable mininter-queue.service
sudo systemctl enable mininter-scheduler.service

# Iniciar servicios
sudo systemctl start mininter-queue.service
sudo systemctl start mininter-scheduler.service
```

## 📊 Verificación y Monitoreo

### Ver estado de servicios
```bash
sudo systemctl status mininter-queue.service
sudo systemctl status mininter-scheduler.service
```

### Ver logs en tiempo real
```bash
# Queue worker logs
sudo journalctl -u mininter-queue.service -f

# Scheduler logs
sudo journalctl -u mininter-scheduler.service -f

# Ambos logs juntos
sudo journalctl -u mininter-queue.service -u mininter-scheduler.service -f
```

### Ver logs históricos
```bash
# Últimas 100 líneas
sudo journalctl -u mininter-queue.service -n 100

# Logs de las últimas 24 horas
sudo journalctl -u mininter-queue.service --since "24 hours ago"
```

## 🔄 Gestión de Servicios

### Reiniciar servicios
```bash
sudo systemctl restart mininter-queue.service
sudo systemctl restart mininter-scheduler.service
```

### Detener servicios
```bash
sudo systemctl stop mininter-queue.service
sudo systemctl stop mininter-scheduler.service
```

### Deshabilitar servicios
```bash
sudo systemctl disable mininter-queue.service
sudo systemctl disable mininter-scheduler.service
```

## 🛠️ Configuración Avanzada

### Cambiar usuario de ejecución

Si necesitas cambiar el usuario que ejecuta los servicios:

```bash
# Editar archivos de servicio
sudo nano /etc/systemd/system/mininter-queue.service
sudo nano /etc/systemd/system/mininter-scheduler.service

# Cambiar líneas:
User=tu-usuario
Group=tu-grupo
```

### Ajustar parámetros del Queue Worker

En `mininter-queue.service`, puedes modificar:

```ini
# Tiempo máximo de ejecución (1 hora)
ExecStart=/usr/bin/php /var/www/mininter/artisan queue:work --queue=sync --daemon --sleep=3 --tries=3 --max-time=3600

# Otros parámetros útiles:
# --timeout=60        # Timeout por job
# --memory=512        # Límite de memoria (MB)
# --max-jobs=1000     # Máximo jobs antes de reiniciar
```

## 📝 Logs del Sistema GPS

Los logs del sistema GPS se almacenan en:

```bash
# Logs de Laravel
tail -f /var/www/mininter/storage/logs/laravel.log

# Logs específicos de GPS
tail -f /var/www/mininter/storage/logs/gps.log
tail -f /var/www/mininter/storage/logs/transmissions.log
```

## 🚨 Solución de Problemas

### Servicio no inicia
```bash
# Ver errores detallados
sudo journalctl -u mininter-queue.service -n 50

# Verificar permisos
sudo chown -R www-data:www-data /var/www/mininter/storage
sudo chown -R www-data:www-data /var/www/mininter/bootstrap/cache
```

### Problemas de conexión a base de datos
```bash
# Verificar configuración
sudo -u www-data php /var/www/mininter/artisan migrate:status

# Probar conexión
sudo -u www-data php /var/www/mininter/artisan tinker --execute="DB::connection()->getPdo();"
```

### Actualizar código
```bash
# Después de actualizar el código, reiniciar servicios
sudo systemctl restart mininter-queue.service
sudo systemctl restart mininter-scheduler.service
```

## 🔐 Seguridad

Los servicios están configurados con:

- ✅ **NoNewPrivileges**: Impide escalada de privilegios
- ✅ **PrivateTmp**: Directorio temporal privado
- ✅ **ProtectSystem**: Protección del sistema de archivos
- ✅ **ReadWritePaths**: Solo escritura en directorios específicos
- ✅ **Usuario específico**: Ejecución con usuario www-data

## 📈 Monitoreo en Producción

### Alertas básicas
```bash
# Crear script de monitoreo
cat > /usr/local/bin/mininter-health.sh << 'EOF'
#!/bin/bash
if ! systemctl is-active --quiet mininter-queue.service; then
    echo "ALERTA: mininter-queue.service está inactivo"
    # Enviar notificación/email aquí
fi
if ! systemctl is-active --quiet mininter-scheduler.service; then
    echo "ALERTA: mininter-scheduler.service está inactivo"
    # Enviar notificación/email aquí
fi
EOF

chmod +x /usr/local/bin/mininter-health.sh

# Programar verificación cada 5 minutos
echo "*/5 * * * * /usr/local/bin/mininter-health.sh" | sudo crontab -
```

---

## 🎯 Resultado Final

Una vez configurado, tendrás:

- ✅ **Queue Worker** procesando jobs GPS automáticamente
- ✅ **Scheduler** ejecutando sincronizaciones cada minuto
- ✅ **Reinicio automático** en caso de fallos
- ✅ **Logs centralizados** en systemd
- ✅ **Inicio automático** al reiniciar servidor
- ✅ **Monitoreo** con comandos systemctl

**¡Tu sistema GPS MININTER funcionará 24/7 sin intervención manual!** 🚀 