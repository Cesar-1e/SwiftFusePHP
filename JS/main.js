var buttonsLoad = [];

/**
 * Al cargar todo el documento se ejecuta las funciones
 * @param {<T>} fun Function Lambda
 */
function onload(fun) {
    document.addEventListener("DOMContentLoaded", function () {
        fun();
    });
}

/**
 * El button establece el disabled en true y cambien su contenido por un loading
 * @param {string} selector Selector del button
 */
const BUTTONLOADING = (selector) => {
    let btn = SELECTOR(selector);
    buttonsLoad[selector] = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<div class="spinner-border text-dark" role="status">
    <span class="visually-hidden">Cargando...</span></div>`;
};

/**
 * El button establece el disabled en false y cambia por su contenido original o personalizado
 * @param {string} selector Selector del button
 * @param {string} text Si es null, establece el valor original del button
 */
const BUTTONLOAD = (selector, text = null) => {
    let btn = SELECTOR(selector);
    if(typeof buttonsLoad[selector] == "undefined" && text == null){
        text = btn.innerHTML;
    }
    btn.disabled = false;
    btn.innerHTML = (text == null ? buttonsLoad[selector] : text);
};

/**
 * Query Selector
 * @param {string} selector El selector del elemento
 * @returns DOM
 */
let SELECTOR = (selector) => {
    return document.querySelector(selector);
};

/**
 * Query Selector All
 * @param {string} selector 
 * @returns DOMs
 */
let SELECTORES = (selector) => {
    return document.querySelectorAll(selector);
};

/**
 * Remove la class de todos los elements
 * @param {string} selector 
 * @param {string} classDOM 
 */
let removeClass = (selector, classDOM) => {
    SELECTORES(selector).forEach((element) => {
        element.classList.remove(classDOM);
    });
};

/**
 * Add class de todos los elements
 * @param {string} selector 
 * @param {string} classDOM 
 */
let addClass = (selector, classDOM) => {
    SELECTORES(selector).forEach((element) => {
        element.classList.add(classDOM);
    });
};

/**
 * Add onclick de todos los elements
 * @param {string} selector 
 * @param {<T>} fun Function lambda
 */
let onclicks = (selector, fun) => {
    SELECTORES(selector).forEach((element) => {
        element.onclick = fun;
    });
};

/**
 * Comunicación asíncrona con el servidor en segundo plano
 * @param {string} url Ruta relativa
 * @param {<T>} fun Function lambda
 * @param {FormData} formData 
 * @param {string} method POST | GET
 */
function ajax(url, fun = null, formData = null, method = "POST") {
    fetch(RUTA + url, {
        method: method,
        body: formData,
    })
        .then((res) => res.json())
        .catch((error) =>
            alert.fire({
                icon: "error",
                title: 'Error interno del servidor',
            })
        )
        .then(fun);
}
/**
 * Descarga el archivo solicitado
 *
 * @param {String} name Nombre del archivo
 * @param {String} url Ruta Relativa
 * @param {<T>} fun Lambda; Optional
 * @param {FormData} formData FormData; Optional
 * @param {String} method POST | GET
 */
function download(name, url, fun = null, formData = null, method = "POST") {
    fetch(RUTA + url, {
        method: method,
        body: formData
    })
        .then((response) => response.blob())
        .then((blob) => {
            let url = window.URL.createObjectURL(blob);
            let a = document.createElement("a");
            a.href = url;
            a.download = name;
            document.body.appendChild(a);
            a.click();
            a.remove();
            if (fun != null) {
                fun();
            }
        });
}

/**
 * Obtiene el object de FormData del form
 * @param {string} selector form
 * @returns {FormData}
 */
function formData(selector) {
    return new FormData(SELECTOR(selector));
}

/**
 * Input solo numbers
 * 
 * Soporta SELECTORES
 * @param {string} selector 
 */
function inputsNumber(selector) {
    isNumber = (value, e) => {
        if (!Number.isInteger(+value) || value == " ") {
            e.preventDefault();
        }
    };
    SELECTORES(selector).forEach((element) => {
        element.onkeypress = (e) => {
            if (e.key != "Enter") {
                isNumber(e.key, e);
            }else if(e.key != "Space") {
                e.preventDefault();
            }
        };

        element.onpaste = (e) => {
            isNumber(e.clipboardData.getData("text"), e);
        };
    });
}

/**
 * Verifica si el text es un email
 * @param {string} text email
 * @returns {bool} True si es un email. False no es un email
 */
function isEmail(text) {
    var mailformat = /^\w+([\.-]?\w+)*@\w+([\.-]?\w+)*(\.\w{2,3})+$/;
    return text.match(mailformat);
}

/**
 * Add option al select de HTML
 * 
 * Si el value o el text se repite no lo agrega y reporta el error en la console
 * @param {Select} select entity HTML
 * @param {string} text El text que se muestra en el option
 * @param {string|int} value El value del option
 * @returns 
 */
function addOption(select, text, value = null) {
    let option = new Option(text.trim(), value);
    //Comprobamos que los valores no se repitan
    for (let i = 0; i < select.options.length; i++) {
        const opt = select.options[i];
        if (
            opt.text == option.text ||
            (opt.value == option.value && option.value != "null")
        ) {
            throw "No podemos añadir el valor, por ser repetido " + option.text + "❗";
            //return false;
        }
    }
    select.add(option);
    return true;
}

/**
 * Desvia el enter a un button especifico y simula el click del button
 * @param {Dom} element 
 * @param {Button} btn 
 */
function divertEnterSubmit(element, btn) {
    element.onkeypress = (e) => {
        if (e.key == "Enter") {
            e.preventDefault();
            btn.onclick();
        }
    };
}

/**
 * Remove all options del select, excepto el primer valor
 * @param {Select} select 
 */
function resetSelect(select) {
    let option = select.options[0];
    select.innerHTML = "";
    select.add(option);
}

/**
 * Set display de todos los elements
 * @param {string} selector 
 * @param {string} mode inline | block | contents | flex | grid	| ...
 */
function displays(selector, mode) {
    SELECTORES(selector).forEach((element) => {
        element.style.display = mode;
    });
}

/**
 * Formato de la moneda y agrega el 💲, si el valor es negativo agrega ➖💲
 * @param {float} number example 2050.55
 * @returns {string} example 💲2,050.55
 */
function formatCurrency(number) {
    var neg = false;
    if (number < 0) {
        neg = true;
        number = Math.abs(number);
    }
    return (neg ? "➖💲" : '💲') + parseFloat(number, 10).toFixed(2).replace(/(\d)(?=(\d{3})+\.)/g, "$1,").toString();
}