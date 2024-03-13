#!/bin/bash
#Shell para comprimir el pdf
#Desarrollado por Cesar-1e
PATH=/usr/local/sbin:/usr/local/bin:/sbin:/bin:/usr/sbin:/usr/bin

readonly RUTA_APP="$1"
input="$2"

function log_error {
    local message="$1"
    "${RUTA_APP}Archivos/scripts/log.sh" "$RUTA_APP" "$message"
}

function assert_is_installed {
    local name="$1"

    if [[ ! $(command -v "${name}") ]]; then
        log_error "El binario '$name' se requiere pero no se encuentra instalado en el sistema"
        exit 1
    fi
}

function compress_pdf {
    local GHOSTSCRIPT
    GHOSTSCRIPT="$(which ghostscript)"

    $GHOSTSCRIPT -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dPDFSETTINGS=/printer -dNOPAUSE -dQUIET -dBATCH -sOutputFile="$input.compress" "$input"
    rm "$input"
    mv "$input.compress" "$input"
}

function run {
    assert_is_installed "ghostscript"
}

run
compress_pdf