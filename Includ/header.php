<?php 
$active = function($vista, $archivo){
    $archivo = "inicio/" . $archivo;
    if($vista == $archivo){echo "class='activeColor'";}
};
?>
<div class="contenedorContacto">
    <ul>
        <li><a href="tel:3208865180" class="link-light"><i class="bi bi-telephone-fill"></i><strong> Atencion
                    Empresarial: </strong> 320 886 5180</a></li>
        <li><a href="https://wa.me/+573112563832" target="_blank" class="link-light"><strong><i
                        class="bi bi-whatsapp"></i></strong> 311 256 3832</a></li>
        <li><a href="mailto:entrenamientonacional@sgi.com.co" class="link-light"><i class="bi bi-envelope-fill"></i>
                entrenamientonacional@sgi.com.co</a></li>

    </ul>
</div>
<header>
    <nav>
        <input type="checkbox" id="check">
        <label for="check" class="checkbtn">


            <div class="menu-toggle">
                <div class="toggle">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </div>

        </label>
        <div class="logo">
            <img src="<?php echo LOGO?>" class="" />
        </div>
        <ul>
            <li><a href="<?php echo RUTA_URL?>" <?php $active($vista, "index");?>><i class="bi bi-house-door-fill"></i>Inicio</a></li>
            <li><a href="Servicios"><i class="bi bi-journals"></i> Cursos</a></li>
            <li><a href="QuienesSomos"><i class="bi bi-building"></i> Quienes Somos</a></li>
            <li><a href="Profesionales"> <i class="bi bi-briefcase-fill"></i> Profesionales</a></li>
            <li><a href="Contacto"><i class="bi bi-envelope-fill"></i> Contacto</a></li>
            <li><a href="<?php echo RUTA_URL . "Inicio/Ingresar"?>" <?php $active($vista, "ingresar");?>><i class="bi bi-person-circle"></i> Ingresar</a>
            </li>
        </ul>
    </nav>
</header>