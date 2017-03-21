<?php 

require_once(dirname(__FILE__).'/../../config/config.inc.php');
include_once(dirname(__FILE__).'/integrator.php');

$co = $_GET['mode'];

$int = new Integrator();

switch($co){
	case 'ceneo':
		$int->generuj('Ceneo');
		break;
	case 'nokaut':
		$int->generuj('Nokaut');
		break;
	case 'skapiec':
		$int->generuj('Skapiec');
		break;
	default:
		break;
}

?>