<?php

/**
 * permet de scannner le dossier et de faire une boucle si fichier png
 * $dir
 * $recursive
 * array
 */

 function scan($dir, $recursive)
{
    $results_array = array();
    if (is_dir($dir)) {
        if ($handle = opendir($dir)) {
            while (($ff = readdir($handle)) !== FALSE) {
                if ($ff != "." && $ff != "..") {
                    if (is_dir($dir . '/' . $ff) && $recursive) {
                        scan($dir . '/' . $ff, $recursive);
                    } else {
                            // exif_imagetype determine le type de l'image
                            if (exif_imagetype($dir . "/" . $ff) == IMAGETYPE_PNG) {
                                $results_array[] = $dir . "/" . $ff;
                            }
                        }
                    }
                }
                closedir($handle);
            }
        }
        return $results_array;
    }



/**
 * $assetsFolder
 * $recursive
 * $overrideSize
 * $padding
 * array
 */
function findMaxWidthAndHeight($assetsFolder, $recursive, $overrideSize, $padding) {
    $imgTotalHeight = 0;
    $imgTotalWidth = 0;
    $tab = scan($assetsFolder, $recursive);
    foreach ($tab as $key => $value) {
        // prend en considération le overrideSize. 400px
        if($overrideSize != 0) {
            $imgWidth = $overrideSize;
            $imgHeight = $overrideSize;
        } else {
            $size = getimagesize($value);
            $imgWidth = $size[0];
            $imgHeight = $size[1];
        }
        if ($imgHeight > $imgTotalHeight) {
            $imgTotalHeight = $imgHeight;
		}
		if($padding != 0 && count($tab)-1 != $key) {
			$imgTotalWidth += $imgWidth + $padding;
		} else {
			$imgTotalWidth += $imgWidth;
		}
    }

    return ["width" => $imgTotalWidth, "height" => $imgTotalHeight];
}

/**
 * $outputImage
 * $imgTotalWidth
 * $imgTotalHeight
 */
function generateBlankImage($outputImage, $imgTotalWidth, $imgTotalHeight) {
    $blankImage = imagecreatetruecolor($imgTotalWidth, $imgTotalHeight);
    imagesavealpha($blankImage, true);
    $trans = imagecolorallocatealpha($blankImage, 0, 0, 0, 127);
    imagecolortransparent($blankImage, $trans);
    imagefill($blankImage, 0, 0, $trans);
    imagealphablending($blankImage, false);
    imagesavealpha($blankImage, true);
    imagepng($blankImage, $outputImage);
    return $blankImage;
}


/**
 * $assetsFolder
 * $recursive
 * $image
 * $outputImage
 * $outputStyle
 * $overrideSize
 * $padding
 */
function mergeImagesAndGenerateCss($assetsFolder, $recursive, $image, $outputImage, $outputStyle, $overrideSize, $padding) {
	$imgTotalWidth = 0; ///  [---][][--][][-----]
	//var_dump($padding);

	$tab = scan($assetsFolder, $recursive);
	var_dump($tab);
    foreach ($tab as $key => $value) {

        // merge all images
        $size = getimagesize($value);
        $imgWidth = $size[0];
        $imgHeight = $size[1];
        $imgTmp = imagecreatefrompng($value);

        if($overrideSize != 0) {
            $imgWidth = $overrideSize;
            $imgHeight = $overrideSize;
            $imgTmp = imagescale($imgTmp, $imgWidth, $imgHeight);
		}
		
        imagesavealpha($imgTmp, true);
        imagealphablending($imgTmp, false);
        imagecopymerge($image, $imgTmp, $imgTotalWidth, 0, 0, 0, $imgWidth, $imgHeight, 100);
        $imgTotalWidth += $imgWidth;

		if($padding != 0 && count($tab)-1 != $key) {
			$im = imagecreate(300, 100);
			imagecolorallocate($im, 255, 0, 0);
			$bg = imagecolorat($im, 0, 0);
			imagecolorset($im, $bg, 0, 0, 0);
			imagepng($im, 'padding.png');
			$imgTmp = imagecreatefrompng('padding.png');
			imagecopymerge($image, $im, $imgTotalWidth, 0, 0, 0, $padding, $paddingHeigth=500, 100);
			$imgTotalWidth += $padding;
			if (file_exists('padding.png')) {
        		unlink('padding.png');
			}
		}

        // generate css
        $fp = fopen($outputStyle, 'a');
        $css = sprintf(".background%s{\n width: %spx;\n height: %spx;\n background-image: url(%s);\n text-align:center;\n background-position: %spx 0px;\n}\n",
            $key, $imgWidth, $imgHeight, $outputImage, $imgTotalWidth);
        fwrite($fp, $css);
        fclose($fp);
    }
    return $image;
}

/**
 * créer une image sprite et un fichier css pour la sprite
 * $assetsFolder
 * $recursive
 * $outputImage
 * $outputStyle
 * $overrideSize
 * $padding
 */
function image($assetsFolder, $recursive, $outputImage, $outputStyle, $overrideSize, $padding)
{
    if (file_exists($outputImage))
        unlink($outputImage);
    if (file_exists($outputStyle))
        unlink($outputStyle);

    $data = findMaxWidthAndHeight($assetsFolder, $recursive, $overrideSize, $padding);
    $imgTotalWidth= $data["width"];
    $imgTotalHeight = $data["height"];

    $blankImage = generateBlankImage($outputImage, $imgTotalWidth, $imgTotalHeight);

    $filledImage = mergeImagesAndGenerateCss($assetsFolder, $recursive, $blankImage, $outputImage, $outputStyle, $overrideSize, $padding);

    // sauvegarder l'image dans ce chemin
    imagepng($filledImage, $outputImage);
}

// Executer l'application
/**
 * comment executer l'application:
 * exemples:
 * php app.php --output-image=coucou mesimages
 * php app.php --output-imagecoucou mesimages
 * php app.php -i=coucou mesimages
 * php app.php -icoucou mesimages
 */
$assetsFolder = $argv[$argc - 1];
$outputImage = "sprite.png";
$outputStyle = "style.css";
$overrideSize = 0;
$recursive = false;
$shortOpt = "";
$shortOpt .= "r::";
$shortOpt .= "i::";
$shortOpt .= "s::";
$shortOpt .= "p::";
$shortOpt .= "o::";
$longOpt = array(
    "recursive::",
    "output-image::",
	"output-style::",
	"padding::",
    "override-size::"
);
$options = getopt($shortOpt, $longOpt);
foreach ($options as $key => $option) {
    switch ($key) {
        case 'r':
        case 'recursive':
            $recursive = true;
            break;

        case 'i':
        case 'output-image':
            if (!is_null($option)) {
                $outputImage = $option;
            }
            break;

        case 's':
        case 'output-style':
            if (!is_null($option)) {
                $outputStyle = $option;
            }
            break;

        case 'p':
		case 'padding':
			if(!is_null($option)) {
				$padding = $option;
			}
            break;

        case 'o':
            if (!is_null($option)) {
                $overrideSize = intval($option);
            }
            break;

		case 'c':
			//non fait pour le moment mais % a utiliser ?? 
            break;
    }
}

if(!is_dir($assetsFolder)) {
    echo "Merci de fournir un dossier\n";
} else {
    image($assetsFolder, $recursive, $outputImage, $outputStyle, $overrideSize, $padding);
}
?>