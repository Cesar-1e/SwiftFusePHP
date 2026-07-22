/**
 * People list (AJAX).
 *
 * Uses the helpers provided by main.js (onload, ajax, SELECTOR, BUTTONLOADING,
 * BUTTONLOAD) to fetch /people/list and render the rows without a full reload.
 * The base URL is taken from the global RUTA defined by the view.
 */

/**
 * Render the people rows into the table body.
 *
 * @param {Array<{peopleId:number,name:string,email:string}>} people - Rows to render.
 */
function renderPeople(people) {
  const body = SELECTOR("#peopleBody");
  if (!people || people.length === 0) {
    body.innerHTML = '<tr><td colspan="3">No people found.</td></tr>';
    return;
  }

  body.innerHTML = people
    .map(
      (person) =>
        `<tr><td>${person.peopleId}</td><td>${escapeHtml(person.name)}</td><td>${escapeHtml(person.email)}</td></tr>`
    )
    .join("");
}

/**
 * Escape a value for safe insertion into HTML.
 *
 * @param {string} value - The raw value.
 * @returns {string} The escaped value.
 */
function escapeHtml(value) {
  const div = document.createElement("div");
  div.textContent = value == null ? "" : String(value);
  return div.innerHTML;
}

/**
 * Load the people list from the server and render it.
 *
 * @param {HTMLButtonElement} [btn=null] - Optional button to show a loading state.
 */
function loadPeople(btn = null) {
  if (btn) {
    BUTTONLOADING("#" + btn.id);
  }
  SELECTOR("#peopleStatus").textContent = "Loading...";

  ajax(
    "people/list",
    (response) => {
      if (response && response.ok) {
        renderPeople(response.data);
        SELECTOR("#peopleStatus").textContent = response.data.length + " person(s).";
      } else {
        SELECTOR("#peopleStatus").textContent =
          (response && response.message) || "Could not load the list.";
      }
      if (btn) {
        BUTTONLOAD("#" + btn.id);
      }
    },
    null,
    "GET"
  );
}

// Load the list automatically when the document is ready.
onload(() => loadPeople());
