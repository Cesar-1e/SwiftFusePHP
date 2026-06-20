function list(btn) {
    BUTTONLOADING("#" + btn.id);
    dvListPeople.innerText = "";
    ajax("Person/List", (response) => {
        if (response.exito) {
            dvListPeople.innerText = JSON.stringify(response.data);
        }else{
            dvListPeople.innerText = response.mensaje;
        }
        BUTTONLOAD("#" + btn.id);
    });
}