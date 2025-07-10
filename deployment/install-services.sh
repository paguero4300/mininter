#!/bin/bash

# ===========================
# MININTER GPS - Instalación de Servicios
# ===========================

set -e

# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Funciones de logging
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Verificar que se ejecuta como root
if [ "$EUID" -ne 0 ]; then
    log_error "Este script debe ejecutarse como root (sudo)"
    exit 1
fi

# Configuración
PROJECT_PATH="/var/www/mininter"
SERVICES_PATH="/etc/systemd/system"

log_info "=== INSTALACIÓN DE SERVICIOS MININTER GPS ==="
log_info "Ruta del proyecto: $PROJECT_PATH"

# Verificar que existe el directorio del proyecto
if [ ! -d "$PROJECT_PATH" ]; then
    log_error "El directorio del proyecto no existe: $PROJECT_PATH"
    log_info "Por favor, ajusta la variable PROJECT_PATH en este script"
    exit 1
fi

# Verificar que existe artisan
if [ ! -f "$PROJECT_PATH/artisan" ]; then
    log_error "No se encontró el archivo artisan en: $PROJECT_PATH"
    exit 1
fi

# Copiar archivos de servicio
log_info "Copiando archivos de servicio a systemd..."
cp mininter-queue.service "$SERVICES_PATH/"
cp mininter-scheduler.service "$SERVICES_PATH/"

# Verificar versión de PHP y ajustar rutas si es necesario
PHP_VERSION=$(php -v | head -n 1 | cut -d' ' -f2 | cut -d'.' -f1,2)
log_info "Versión de PHP detectada: $PHP_VERSION"

# Actualizar archivos de servicio con rutas correctas
sed -i "s|/var/www/mininter|$PROJECT_PATH|g" "$SERVICES_PATH/mininter-queue.service"
sed -i "s|/var/www/mininter|$PROJECT_PATH|g" "$SERVICES_PATH/mininter-scheduler.service"
sed -i "s|/etc/php/8.2/|/etc/php/$PHP_VERSION/|g" "$SERVICES_PATH/mininter-queue.service"
sed -i "s|/etc/php/8.2/|/etc/php/$PHP_VERSION/|g" "$SERVICES_PATH/mininter-scheduler.service"

# Recargar systemd
log_info "Recargando systemd..."
systemctl daemon-reload

# Habilitar servicios
log_info "Habilitando servicios..."
systemctl enable mininter-queue.service
systemctl enable mininter-scheduler.service

# Iniciar servicios
log_info "Iniciando servicios..."
systemctl start mininter-queue.service
systemctl start mininter-scheduler.service

# Verificar estado
log_info "Verificando estado de los servicios..."
echo ""
log_info "=== ESTADO DE SERVICIOS ==="

if systemctl is-active --quiet mininter-queue.service; then
    log_success "✅ mininter-queue.service - ACTIVO"
else
    log_error "❌ mininter-queue.service - INACTIVO"
fi

if systemctl is-active --quiet mininter-scheduler.service; then
    log_success "✅ mininter-scheduler.service - ACTIVO"
else
    log_error "❌ mininter-scheduler.service - INACTIVO"
fi

echo ""
log_info "=== COMANDOS ÚTILES ==="
echo "# Ver estado de servicios:"
echo "sudo systemctl status mininter-queue.service"
echo "sudo systemctl status mininter-scheduler.service"
echo ""
echo "# Ver logs en tiempo real:"
echo "sudo journalctl -u mininter-queue.service -f"
echo "sudo journalctl -u mininter-scheduler.service -f"
echo ""
echo "# Reiniciar servicios:"
echo "sudo systemctl restart mininter-queue.service"
echo "sudo systemctl restart mininter-scheduler.service"
echo ""
echo "# Detener servicios:"
echo "sudo systemctl stop mininter-queue.service"
echo "sudo systemctl stop mininter-scheduler.service"

log_success "¡Instalación completada exitosamente!"
log_info "Los servicios se ejecutarán automáticamente al reiniciar el servidor" 