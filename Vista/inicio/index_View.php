<!DOCTYPE html>
<html lang="en">
<head>
    <?php require_once RUTA_APP . "Includ/head.php";?>
    <script src="<?= RUTA_URL;?>JS/person.js"></script>
    <title><?php echo NOMBRESITIO;?></title>
</head>
<body>
    <h1>Editame</h1>
    <h2>Button loading</h2>
    <button id="btn1" onclick="btnLoadingExample(this)">Primer button</button>
    <button id="btn2" onclick="btnLoadingExample(this)">Segundo button</button>
    <button onclick="btnLoadExample(this)">Reestablecer buttons</button>

    <p id="currency">2050.55</p>

    <br>
    <button id="btnListPeople" onclick="list(this)">Listar personas en JSON</button>
    <br>
    <br>
    <div id="dvListPeople">

    </div>

    <script>
        onload(() => {
            currency.innerText = formatCurrency(currency.innerText);
        });
        
        function btnLoadingExample(btn){
            BUTTONLOADING("#" + btn.id);
        }

        function btnLoadExample(){
            BUTTONLOAD("#btn1");
            BUTTONLOAD("#btn2");
        }
    </script>
</body>
</html>