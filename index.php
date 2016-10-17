<?php
/**
 * The Unzipper extracts .zip or .rar archives and .gz files on webservers.
 * It's handy if you do not have shell access. E.g. if you want to upload a lot
 * of files (php framework or image collection) as an archive to save time.
 * As of version 0.1.0 it also supports creating archives.
 *
 * @author  Andreas Tasch, at[tec], attec.at; Stefan Boguth, Boguth.org
 * @license GNU GPL v3
 * @version 0.1.4
 */
define('VERSION', '0.1.4');
$timestart = microtime(TRUE);
$GLOBALS['status'] = array();
$unzipper = new Unzipper;
if (isset($_POST['dounzip'])) {
  //check if an archive was selected for unzipping
  $archive = isset($_POST['zipfile']) ? strip_tags($_POST['zipfile']) : '';
  $destination = isset($_POST['extpath']) ? strip_tags($_POST['extpath']) : '';
  $unzipper->prepareExtraction($archive, $destination);
}
if (isset($_POST['dozip'])) {
  $zippath = !empty($_POST['zippath']) ? strip_tags($_POST['zippath']) : '.';
  // Resulting zipfile e.g. zipper--2016-07-23--11-55.zip
  $zipfile = 'zipper-' . date("Y-m-d--H-i") . '.zip';
  Zipper::zipDir($zippath, $zipfile);
}
if (isset($_POST['upload'])) {
    $filename = "upload/";

    //checking if dir upload exists, if not create it.
    if (!file_exists($filename)) {
        mkdir($filename, 0755);
    }
    //moving the uploaded files to the upload-dir
    $upload_folder = 'upload/'; //Das Upload-Verzeichnis
    $filename = pathinfo($_FILES['uploaded']['name'], PATHINFO_FILENAME);
    $extension = strtolower(pathinfo($_FILES['uploaded']['name'], PATHINFO_EXTENSION));


    //Überprüfung der Dateiendung
    $allowed_extensions = array('rar', 'zip', 'gz');
    if(!in_array($extension, $allowed_extensions)) {
    	die("Ungültige Dateiendung. Nur .rar, .zip und .gz sind erlaubt.");
    }

    //Überprüfung der Dateigröße
    $max_size = 10000*1024; //10 MB
    if($_FILES['uploaded']['size'] > $max_size) {
    	die("Bitte keine Dateien größer 10 MB hochladen.");
    }

    //Pfad zum Upload
    $new_path = $upload_folder.$filename.'.'.$extension;

    //Neuer Dateiname falls die Datei bereits existiert
    if(file_exists($new_path)) { //Falls Datei existiert, hänge eine Zahl an den Dateinamen
    	$id = 1;
    	do {
    		$new_path = $upload_folder.$filename.'_'.$id.'.'.$extension;
    		$id++;
    	} while(file_exists($new_path));
    }

    //Alles okay, verschiebe Datei an neuen Pfad
    move_uploaded_file($_FILES['uploaded']['tmp_name'], $new_path);
}
$timeend = microtime(TRUE);
$time = $timeend - $timestart;
$timestring = (string)$time;
/**
 * Class Unzipper
 */
class Unzipper {
  public $localdir = '.';
  public $zipfiles = array();
  public function __construct() {
    //read directory and pick .zip and .gz files
    if ($dh = opendir($this->localdir)) {
      while (($file = readdir($dh)) !== FALSE) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'zip'
          || pathinfo($file, PATHINFO_EXTENSION) === 'gz'
          || pathinfo($file, PATHINFO_EXTENSION) === 'rar'
        ) {
          $this->zipfiles[] = $file;
        }
      }
      closedir($dh);
      if (!empty($this->zipfiles)) {
        $GLOBALS['status'] = array('info' => '.zip, .gz oder .rar Dateien gefunden, bereit zum entpacken.');
      }
      else {
        $GLOBALS['status'] = array('info' => 'Keine .zip oder .gz oder rar Dateien gefunden. Nur Funktionen zum Packen von Archiven und Dateiupload möglich.');
      }
    }
  }
  /**
   * Prepare and check zipfile for extraction.
   *
   * @param $archive
   * @param $destination
   */
  public function prepareExtraction($archive, $destination) {
    // Determine paths.
    if (empty($destination)) {
      $extpath = $this->localdir;
    }
    else {
      $extpath = $this->localdir . '/' . $destination;
      // todo move this to extraction function
      if (!is_dir($extpath)) {
        mkdir($extpath);
      }
    }
    //allow only local existing archives to extract
    if (in_array($archive, $this->zipfiles)) {
      self::extract($archive, $extpath);
    }
  }
  /**
   * Checks file extension and calls suitable extractor functions.
   *
   * @param $archive
   * @param $destination
   */
  public static function extract($archive, $destination) {
    $ext = pathinfo($archive, PATHINFO_EXTENSION);
    switch ($ext) {
      case 'zip':
        self::extractZipArchive($archive, $destination);
        break;
      case 'gz':
        self::extractGzipFile($archive, $destination);
        break;
      case 'rar':
        self::extractRarArchive($archive, $destination);
        break;
    }
  }
  /**
   * Decompress/extract a zip archive using ZipArchive.
   *
   * @param $archive
   * @param $destination
   */
  public static function extractZipArchive($archive, $destination) {
    // Check if webserver supports unzipping.
    if (!class_exists('ZipArchive')) {
      $GLOBALS['status'] = array('error' => 'Fehler: Ihre PHP-Version unterstützt keine unzip-Fähigkeiten.');
      return;
    }
    $zip = new ZipArchive;
    // Check if archive is readable.
    if ($zip->open($archive) === TRUE) {
      // Check if destination is writable
      if (is_writeable($destination . '/')) {
        $zip->extractTo($destination);
        $zip->close();
        $GLOBALS['status'] = array('success' => 'Dateien erfolgreich entpackt!');
      }
      else {
        $GLOBALS['status'] = array('error' => 'Fehler: Verzeichnis nicht vom Webserver schreibbar.');
      }
    }
    else {
      $GLOBALS['status'] = array('error' => 'Fehler: Archiv nicht lesbar!');
    }
  }
  /**
   * Decompress a .gz File.
   *
   * @param $archive
   * @param $destination
   */
  public static function extractGzipFile($archive, $destination) {
    // Check if zlib is enabled
    if (!function_exists('gzopen')) {
      $GLOBALS['status'] = array('error' => 'Fehler: Your PHP has no zlib support enabled.');
      return;
    }
    $filename = pathinfo($archive, PATHINFO_FILENAME);
    $gzipped = gzopen($archive, "rb");
    $file = fopen($filename, "w");
    while ($string = gzread($gzipped, 4096)) {
      fwrite($file, $string, strlen($string));
    }
    gzclose($gzipped);
    fclose($file);
    // Check if file was extracted.
    if (file_exists($destination . '/' . $filename)) {
      $GLOBALS['status'] = array('success' => 'Datei erfolgreich entpackt.');
    }
    else {
      $GLOBALS['status'] = array('error' => 'Fehler beim entpacken der Datei.');
    }
  }
  /**
   * Decompress/extract a Rar archive using RarArchive.
   *
   * @param $archive
   * @param $destination
   */
  public static function extractRarArchive($archive, $destination) {
    // Check if webserver supports unzipping.
    if (!class_exists('RarArchive')) {
      $GLOBALS['status'] = array('error' => 'Error: Your PHP version does not support .rar archive functionality. <a class="info" href="http://php.net/manual/en/rar.installation.php" target="_blank">How to install RarArchive</a>');
      return;
    }
    // Check if archive is readable.
    if ($rar = RarArchive::open($archive)) {
      // Check if destination is writable
      if (is_writeable($destination . '/')) {
        $entries = $rar->getEntries();
        foreach ($entries as $entry) {
          $entry->extract($destination);
        }
        $rar->close();
        $GLOBALS['status'] = array('success' => 'Datei erfolreich entpackt.');
      }
      else {
        $GLOBALS['status'] = array('error' => 'Fehler: Verzeichnis nicht vom Webserver schreibbar.');
      }
    }
    else {
      $GLOBALS['status'] = array('error' => 'Error: Archiv nicht lesbar!');
    }
  }
}
/**
 * Class Zipper
 *
 * Copied and slightly modified from http://at2.php.net/manual/en/class.ziparchive.php
 * @author umbalaconmeogia
 */
class Zipper {
  /**
   * Add files and sub-directories in a folder to zip file.
   *
   * @param string     $folder
   *   Path to folder that should be zipped.
   *
   * @param ZipArchive $zipFile
   *   Zipfile where files end up.
   *
   * @param int        $exclusiveLength
   *   Number of text to be exclusived from the file path.
   */
  private static function folderToZip($folder, &$zipFile, $exclusiveLength) {
    $handle = opendir($folder);
    while (FALSE !== $f = readdir($handle)) {
      // Check for local/parent path or zipping file itself and skip.
      if ($f != '.' && $f != '..' && $f != basename(__FILE__)) {
        $filePath = "$folder/$f";
        // Remove prefix from file path before add to zip.
        $localPath = substr($filePath, $exclusiveLength);
        if (is_file($filePath)) {
          $zipFile->addFile($filePath, $localPath);
        }
        elseif (is_dir($filePath)) {
          // Add sub-directory.
          $zipFile->addEmptyDir($localPath);
          self::folderToZip($filePath, $zipFile, $exclusiveLength);
        }
      }
    }
    closedir($handle);
  }
  /**
   * Zip a folder (including itself).
   * Usage:
   *   Zipper::zipDir('path/to/sourceDir', 'path/to/out.zip');
   *
   * @param string $sourcePath
   *   Relative path of directory to be zipped.
   *
   * @param string $outZipPath
   *   Relative path of the resulting output zip file.
   */
  public static function zipDir($sourcePath, $outZipPath) {
    $pathInfo = pathinfo($sourcePath);
    $parentPath = $pathInfo['dirname'];
    $dirName = $pathInfo['basename'];
    $z = new ZipArchive();
    $z->open($outZipPath, ZipArchive::CREATE);
    $z->addEmptyDir($dirName);
    if ($sourcePath == $dirName) {
      self::folderToZip($sourcePath, $z, 0);
    }
    else {
      self::folderToZip($sourcePath, $z, strlen("$parentPath/"));
    }
    $z->close();
    $GLOBALS['status'] = array('success' => 'Archiv erfolgreich erstellt: ' . $outZipPath);
  }
}



?>

<!DOCTYPE html>
<html>
<head>
  <title>File Unzipper + Zipper</title>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
  <link href="https://fonts.googleapis.com/css?family=Ubuntu" rel="stylesheet">
  <link rel="stylesheet" href="resources/animate.css">
  <link rel="stylesheet" href="resources/style.css">
</head>
<body>
  <div class="animated zoomIn wrapper">
    <div class="innerwrapper">
      <p class="status status--<?php echo strtoupper(key($GLOBALS['status'])); ?>">
        Status: <?php echo reset($GLOBALS['status']); ?><br/>
        <span class="small">Zur Scriptausführung benötigte Zeit: <?php echo substr($timestring,0,4); ?> Sekunden</span>
      </p>
      <form action="" method="POST">
        <fieldset>
          <h1>Archiv Unzipper</h1>
          <label for="zipfile">Wählen Sie ein .rar, .zip oder .gz-Archiv das sie entpacken wollen:</label>
          <select name="zipfile" size="1" class="select">
            <?php foreach ($unzipper->zipfiles as $zip) {
              echo "<option>$zip</option>";
            }
            ?>
          </select>
            <?php if (count($zip) == 0) {
                    echo "<b>Keine Dateien zum entpacken vorhanden!</b>";
                  };
            ?>
          <label for="extpath">Pfad zum Entpacken (Optional):</label>
          <input type="text" name="extpath" class="form-field" />
          <p class="info">Den gewünschten Pfad ohne Slash am Anfang oder Ende eingeben (z.B. "meinPfad").<br> Wenn das Feld leergelassen wird dann wird das Archiv im selben Pfad entpackt.</p>
          <input type="submit" name="dounzip" class="submit" value="Entpacken"/>
        </fieldset>

        <fieldset>
          <h1>Archiv Zipper</h1>
          <label for="zippath">Pfad den Sie zippen wollen (Optional):</label>
          <input type="text" name="zippath" class="form-field" />
          <p class="info">Den gewünschten Pfad ohne Slash am Anfang oder Ende eingeben (z.B. "meinPfad").<br> Wenn das Feld leergelassen wird dann wird der aktuelle Pfad verwendet.</p>
          <input type="submit" name="dozip" class="submit" value="Packen"/>
        </fieldset>
      </form>
      <form action ="" method="POST" enctype="multipart/form-data">
        <fieldset>
          <h1>Archiv-Uploader</h1>
          <label for="uploader">Hochzuladende Datei:</label>
          <input type="file" name="uploaded" class="form-field" />
          <p class="info">Der Uploader benötigt Schreibrechte im Verzeichnis! Die Dateien werden in den Pfad Upload verschoben.<br> <b>Der Uploader akzeptiert nur .rar, .zip und .gz-Dateien.</b></p>
          <input type="submit" name="upload" class="submit" value="Hochladen"/>
        </fieldset>
      </form>
      <p class="version">Unzipper Version: <?php echo VERSION; ?></p>
    </div>
  </div>
</body>
</html>