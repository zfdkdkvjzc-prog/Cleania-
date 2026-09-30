<?php
require 'db.php';
exigir_post();
iniciar_sesion();

unset($_SESSION['aliada']);
session_regenerate_id(true);

responder(["success" => true]);
