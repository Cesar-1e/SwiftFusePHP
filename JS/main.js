var buttonsLoad = [];

/**
 * Ejecuta una función cuando se carga completamente el documento.
 *
 * @param {Function} fun - Una función lambda que se ejecutará al cargar completamente el documento.
 */
function onload(fun) {
  document.addEventListener("DOMContentLoaded", function () {
    fun();
  });
}

/**
 * Deshabilita un botón y cambia su contenido por un indicador de carga.
 *
 * @param {string} selector - Selector del botón que se va a deshabilitar.
 */
const BUTTONLOADING = (selector) => {
  let btn = SELECTOR(selector);
  buttonsLoad[selector] = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = `<div class="spinner-border text-dark" role="status">
    <span class="visually-hidden">Cargando...</span></div>`;
};

/**
 * Habilita un botón y restaura su contenido original o personalizado.
 *
 * @param {string} selector - Selector del botón que se va a habilitar.
 * @param {string} [text=null] - Texto opcional para establecer como contenido del botón. Si es null, se restablece el valor original del botón.
 */
const BUTTONLOAD = (selector, text = null) => {
  let btn = SELECTOR(selector);
  if (typeof buttonsLoad[selector] === "undefined" && text === null) {
    text = btn.innerHTML;
  }
  btn.disabled = false;
  btn.innerHTML = text === null ? buttonsLoad[selector] : text;
};

/**
 * Selecciona un elemento del DOM utilizando un selector.
 *
 * @param {string} selector - El selector del elemento a seleccionar.
 * @returns {DOMElement} El elemento seleccionado.
 */
let SELECTOR = (selector) => {
  return document.querySelector(selector);
};

/**
 * Selecciona múltiples elementos del DOM utilizando un selector.
 *
 * @param {string} selector - El selector para seleccionar los elementos.
 * @returns {NodeList} Una lista de elementos del DOM seleccionados.
 */
let SELECTORES = (selector) => {
  return document.querySelectorAll(selector);
};

/**
 * Elimina una clase de todos los elementos seleccionados.
 *
 * @param {string} selector - El selector para seleccionar los elementos.
 * @param {string} classDOM - La clase que se va a eliminar de los elementos.
 */
let removeClass = (selector, classDOM) => {
  SELECTORES(selector).forEach((element) => {
    element.classList.remove(classDOM);
  });
};

/**
 * Agrega una clase a todos los elementos seleccionados.
 *
 * @param {string} selector - El selector para seleccionar los elementos.
 * @param {string} classDOM - La clase que se va a agregar a los elementos.
 */
let addClass = (selector, classDOM) => {
  SELECTORES(selector).forEach((element) => {
    element.classList.add(classDOM);
  });
};

/**
 * Asigna un evento onclick a todos los elementos seleccionados.
 *
 * @param {string} selector - El selector para seleccionar los elementos.
 * @param {Function} fun - Una función lambda que se asignará como evento onclick.
 */
let onclicks = (selector, fun) => {
  SELECTORES(selector).forEach((element) => {
    element.onclick = fun;
  });
};

/**
 * Realiza una comunicación asíncrona con el servidor en segundo plano.
 * Si se produce un error, se ejecutará la función `errorAjax` si está declarada; de lo contrario, se imprimirá el error en la consola.
 *
 * @param {string} url - Ruta relativa a la cual se realizará la petición.
 * @param {Function} [fun=null] - Una función lambda que se ejecutará con la respuesta del servidor.
 * @param {FormData} [formData=null] - Datos del formulario que se enviarán en la petición.
 * @param {string} [method="POST"] - Método HTTP utilizado en la petición (POST o GET).
 */
function ajax(url, fun = null, formData = null, method = "POST") {
  fetch(RUTA + url, {
    method: method,
    body: formData,
  })
    .then((res) => res.json())
    .catch((error) =>
      typeof errorAjax === "undefined"
        ? console.log("Error interno del servidor: " + error)
        : errorAjax()
    )
    .then(fun);
}

/**
 * Descarga el archivo solicitado.
 *
 * @param {String} name - Nombre del archivo a descargar.
 * @param {String} url - Ruta relativa desde donde se descargará el archivo.
 * @param {Function} [fun=null] - Una función lambda opcional que se ejecutará después de la descarga.
 * @param {FormData} [formData=null] - Datos del formulario que se enviarán en la petición.
 * @param {String} [method="POST"] - Método HTTP utilizado en la petición (POST o GET).
 */
function download(name, url, fun = null, formData = null, method = "POST") {
  fetch(RUTA + url, {
    method: method,
    body: formData,
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
 * Obtiene el objeto FormData del formulario.
 *
 * @param {string} selector - Selector del formulario.
 * @returns {FormData} - Objeto FormData del formulario.
 */
function formData(selector) {
  return new FormData(SELECTOR(selector));
}

/**
 * Permite ingresar solo números en los campos de entrada.
 *
 * Soporta SELECTORES.
 *
 * @param {string} selector - Selector de los campos de entrada.
 */
function inputsNumber(selector) {
  isNumber = (value, e) => {
    if (!Number.isInteger(+value) || value === " ") {
      e.preventDefault();
    }
  };

  SELECTORES(selector).forEach((element) => {
    element.onkeypress = (e) => {
      if (e.key !== "Enter") {
        isNumber(e.key, e);
      } else if (e.key !== "Space") {
        e.preventDefault();
      }
    };

    element.onpaste = (e) => {
      isNumber(e.clipboardData.getData("text"), e);
    };
  });
}

/**
 * Verifica si el texto es una dirección de correo electrónico válida.
 *
 * @param {string} text - Texto que se verificará si es un email.
 * @returns {boolean} - Devuelve true si el texto es un email válido, de lo contrario, devuelve false.
 */
function isEmail(text) {
  var mailformat = /^\w+([\.-]?\w+)*@\w+([\.-]?\w+)*(\.\w{2,3})+$/;
  return text.match(mailformat);
}

/**
 * Agrega una opción al elemento select de HTML.
 *
 * Si el valor o el texto se repiten, no se agrega y se reporta el error en la consola.
 *
 * @param {HTMLSelectElement} select - Elemento select de HTML al cual se agregará la opción.
 * @param {string} text - El texto que se mostrará en la opción.
 * @param {string|int|null} [value=null] - El valor del option. Valor predeterminado es null.
 * @returns {boolean} - Devuelve true si la opción se agregó correctamente, de lo contrario, devuelve false.
 */
function addOption(select, text, value = null) {
  let option = new Option(text.trim(), value);

  // Comprobamos que los valores no se repitan
  for (let i = 0; i < select.options.length; i++) {
    const opt = select.options[i];
    if (
      opt.text === option.text ||
      (opt.value === option.value && option.value !== "null")
    ) {
      console.error("No se puede agregar el valor debido a que está repetido: " + option.text + "❗");
      return false;
    }
  }

  select.add(option);
  return true;
}

/**
 * Desvía la tecla Enter a un botón específico y simula el clic del botón.
 *
 * @param {HTMLElement} element - Elemento DOM al cual se aplicará la desviación.
 * @param {HTMLButtonElement} btn - Botón al cual se desviará y se simulará el clic.
 */
function divertEnterSubmit(element, btn) {
  element.onkeypress = (e) => {
    if (e.key === "Enter") {
      e.preventDefault();
      btn.onclick();
    }
  };
}

/**
 * Elimina todas las opciones del select, excepto la primera opción.
 *
 * @param {HTMLSelectElement} select - Elemento select al cual se eliminarán las opciones.
 */
function resetSelect(select) {
  let option = select.options[0];
  select.innerHTML = "";
  select.add(option);
}

/**
 * Establece la propiedad de visualización (display) para todos los elementos seleccionados.
 *
 * @param {string} selector - Selector para identificar los elementos.
 * @param {string} mode - Modo de visualización a establecer (inline, block, contents, flex, grid, ...).
 */
function displays(selector, mode) {
  SELECTORES(selector).forEach((element) => {
    element.style.display = mode;
  });
}

/**
 * Formatea un número como una cadena de moneda y agrega el símbolo de moneda.
 * Si el valor es negativo, se agrega el símbolo de negativo y el símbolo de moneda.
 *
 * @param {number} number - Número que se formateará como moneda.
 * @returns {string} - Cadena formateada como moneda, con el símbolo de moneda y símbolo de negativo si corresponde.
 */
function formatCurrency(number) {
  var neg = false;
  if (number < 0) {
    neg = true;
    number = Math.abs(number);
  }
  return (
    (neg ? "➖💲" : "💲") +
    parseFloat(number)
      .toFixed(2)
      .replace(/(\d)(?=(\d{3})+\.)/g, "$1,")
      .toString()
  );
}

/**
 * Descarga un archivo utilizando un objeto Blob o una URL de datos.
 *
 * @param {Blob|String} blob - Objeto Blob o URL de datos para descargar.
 * @param {String} name - Nombre del archivo descargado.
 * @param {Function} [fun=() => {}] - Función que se ejecutará después de finalizar la descarga.
 */
function downloadElement(blob, name, fun = () => { }) {
  if (typeof blob != "string") {
    blob = window.URL.createObjectURL(blob);
  }
  let url = blob;
  let a = document.createElement("a");
  a.href = url;
  a.download = name;
  document.body.appendChild(a);
  a.click();
  a.remove();
  fun();
}

/**
 * Desplaza el scroll de la página hasta un elemento específico.
 *
 * @param {HTMLElement} element - Elemento al que se redireccionará el scroll.
 */
function scrollToElement(element) {
  element.scrollIntoView({
    behavior: 'smooth'
  });
}