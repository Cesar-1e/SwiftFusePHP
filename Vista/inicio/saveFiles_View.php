<!DOCTYPE html>
<html lang="en">

<head>
    <?php require_once RUTA_APP . "Includ/head.php"; ?>
    <title><?php echo NOMBRESITIO; ?></title>
</head>

<body>
    <h1>Guardar archivos</h1>
    <h2>Imagenes</h2>
    <form action="javascript: saveImg();" method="post" enctype="multipart/form-data" id="frmImageUpload">
        <label>Un archivo de imagen</label>
        <input type="file" name="imageFile" accept="image/*">
        <br>
        <label>Varios archivos de imagen</label>
        <input type="file" name="imageFiles[]" accept="image/*" multiple>
        <br><br>
        <input type="submit" value="Subir Imagen">
    </form>
    <span id="spanFilesImg"></span>
    <script>
        function saveImg() {
            let parameters = formData("#frmImageUpload");

            ajax("Upload_Old/Img", (response) => {
                if (response.exito) {
                    alert("Imagen guardada correctamente");
                    response.data.forEach(file => {
                        let imgElement = document.createElement("img");
                        imgElement.src = RUTA + "Public/Uploads/Images/" + file;
                        imgElement.width = 200;
                        document.getElementById("spanFilesImg").appendChild(imgElement);
                    });
                } else {
                    alert(response.mensaje);
                }
            }, parameters);
        }
    </script>
</body>

</html>