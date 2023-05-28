#!/bin/bash
#Shell encargado en registrar logs
#Desarrollado por Cesar-1e
PATH=/usr/local/sbin:/usr/local/bin:/sbin:/bin:/usr/sbin:/usr/bin

readonly RUTA_APP="$1"
readonly MESSAGE="$2"

php "$RUTA_APP/index.php" "$MESSAGE"