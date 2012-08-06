<?php

if (empty($_FILES['archive'])) {
	include('form.html');
	exit;
}

$tmp_base = sys_get_temp_dir();
$tmp_name = uniqid();
$tmp_dir = $tmp_base.'/'.$tmp_name;
mkdir($tmp_dir);
chdir($tmp_dir);


try {
	if (!preg_match('/.+\.zip$/i', $_FILES['archive']['name']))
		throw new Exception("Only .zip files are allowed.");
	
	$output = shell_exec('unzip '.escapeshellarg($_FILES['archive']['tmp_name']));
	if (is_null($output))
		throw new Exception("Could not unzip ".$_FILES['archive']['name'].". Either the file is corrupted or not a valid zip file.");
	
	$zip_contents = scandir($tmp_dir);
	if (count($zip_contents) > 3)
		throw new Exception("Invalid archive file. The archive should only have a single folder in it.");
	$working_dir_name = $zip_contents[2];
	$working_dir = $tmp_dir.'/'.$working_dir_name;
	
	$index_file = $working_dir.'/index.html';
	if (!file_exists($index_file))
		throw new Exception('Invalid archive file. '.$working_dir_name.'/index.html was not found in the archive.');
	
	if (!preg_match("#<link rel='stylesheet' type='text/css' href='content/([^']+)'/>\n\t</head>#", file_get_contents($index_file), $matches))
		throw new Exception("Couldn't find a theme CSS entry in the HTML. Has the file been modified mannually?");
	
	$old_css = $matches[1];
	if (preg_match('/.+\.css/', $old_css))
		throw new Exception("It looks like this Archive has already been fixed. There is nothing to do.");
	
	// Move the theme CSS
	$new_css = $old_css.'.css';	
	rename($working_dir.'/content/'.$old_css, $working_dir.'/content/'.$new_css);
	
	// Update the CSS path in the index.
	$old_link = "<link rel='stylesheet' type='text/css' href='content/".$old_css."'/>\n\t</head>";
	$new_link = "<link rel='stylesheet' type='text/css' href='content/".$new_css."'/>\n\t</head>";
	file_put_contents($index_file, str_replace($old_link, $new_link, file_get_contents($index_file)));
	
	// Update the CSS paths in all of the HTML files
	$old_link = "<link rel='stylesheet' type='text/css' href='".$old_css."'/>\n\t</head>";
	$new_link = "<link rel='stylesheet' type='text/css' href='".$new_css."'/>\n\t</head>";
	foreach (scandir($working_dir.'/content') as $file) {
		$file_path = $working_dir.'/content/'.$file;
		if (preg_match('/.+\.html$/', $file)) {
			file_put_contents($file_path, str_replace($old_link, $new_link, file_get_contents($file_path)));
		}
	}
	
	// Zip up the result
	$output_zip = 'fixed.zip';
	$output = shell_exec('zip -qr '.escapeshellarg($output_zip).' '.escapeshellarg($working_dir_name));
	header('Content-Type: application/zip');
	header('Content-Length: '.filesize($output_zip));
	header('Content-Disposition: attachment; filename='.$_FILES['archive']['name']);
	print file_get_contents($output_zip);

	// Clean up
	shell_exec('rm -rf '.escapeshellarg($tmp_dir));
} catch (Exception $e) {
	if (is_dir($tmp_dir))
		shell_exec('rm -rf '.escapeshellarg($tmp_dir));
	
	print "<html>
	<head>
		<title>Error</title>
	</head>
	<body>
		<h2>An error has occurred:</h2>
		<p>".htmlentities($e->getMessage())."</p>
		<p> &nbsp; </p>
		<p><a href=''>&laquo; Back</a></p>
	</body>
</html>";
}