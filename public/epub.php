<?php

// receive node and user id information from parameter

$host = 'http://localhost/epub/epubs/download/';

$watermark = urldecode($_REQUEST['watermark']);

$target = $_REQUEST['target'];

$nid = $_REQUEST['nid'];

$token = $_REQUEST['token'];

$verified_token = md5($nid.' Blaetter Secret Token '.urlencode($watermark).' '.strftime("%d.%m.%Y").' '.$target);

// If no information is provided, cancel request at this point.
if (empty($watermark) || empty($target) || empty($nid) || empty($token) || $token !== $verified_token) {
	header("HTTP/1.0 400 Bad Request");
}

$epub = $target.'.epub';

$path = exec('pwd');

$original_path = exec('cd .. && pwd');

$tempname = $path.'/epubs/download/'.$epub.'.'.$verified_token;

if (file_exists($tempname)) 
{
	// direct download - file is ready for transferring.
	//exec('rm -rf '.$tempname);
}



// kopie erzeugen
exec('cp -r '.$original_path.'/private/epubs/'.$epub.' '.$tempname, $out, $return);

// Ersetzung mit der Lizensierung: Einmal für originale ePubs (ohne Text)
// und einmal für mit Google bearbeitete ePubs - mit Text.

$cover = file_get_contents($tempname.'/OEBPS/000_Cover.xhtml');

if ($cover) 
{
	// Namen einsetzen
	file_put_contents($tempname.'/OEBPS/000_Cover.xhtml', preg_replace('/XXXXX/i', '<div style="text-align:center;font-family:Verdana;font-size:11px;">Lizensiert für '.$watermark.'</div>', $cover));
}
else
{
	$cover = file_get_contents($tempname.'/OEBPS/Text/000_Cover.xhtml');
	file_put_contents($tempname.'/OEBPS/Text/000_Cover.xhtml', preg_replace('/XXXXX/i', '<div style="text-align:center;font-family:Verdana;font-size:11px;">Lizensiert für '.$watermark.'</div>', $cover));
}

$imprint = file_get_contents($tempname.'/OEBPS/042_Impressum.xhtml');

if ($imprint)
{
	file_put_contents($tempname.'/OEBPS/042_Impressum.xhtml', preg_replace('/XXXXX/i', '<div style="text-align:center;font-family:Verdana;font-size:11px;">Lizensiert für '.$watermark.'</div>', $imprint));	
}
else 
{
	$imprint = file_get_contents($tempname.'/OEBPS/Text/042_Impressum.xhtml');

	file_put_contents($tempname.'/OEBPS/Text/042_Impressum.xhtml', preg_replace('/XXXXX/i', '<div style="text-align:center;font-family:Verdana;font-size:11px;">Lizensiert für '.$watermark.'</div>', $imprint));

}


sleep(1);

exec('cd '.$tempname.' && zip -0Xq '.$epub.' mimetype && zip -Xr9Dq '.$epub.' *');


print_r($host.$epub.'.'.$verified_token.'/'.$epub);

?>