# chunks-uploader


[![Software License](https://img.shields.io/badge/license-GPL-brightgreen.svg?style=flat-square)](LICENSE)
[![Packagist Version](https://img.shields.io/packagist/v/Mehrdad-Dadkhah/chunks-uploader.svg?style=flat-square)](https://packagist.org/packages/mehrdad-dadkhah/chunks-uploader)


uploader to uplaod chunks of a file and combine them use to upload big files.



## Installation

```
composer require mehrdad-dadkhah/chunks-uploader
```

## Usage

```PHP
use MehrdadDadkhah\Video\ChunksUploader;

$uploadHandeler = new ChunksUploader();
$uploadHandeler->setMainFileName('myFile.mp4') //main file name
            ->setFileTotalSize($_REQUEST['totalfilesize']) //size of main file (big file)
            ->setInputName('file') //your form input file name
            ->setChunksFolderPath('path-to-chunks-folder') //path to folder for upload chunks files
            ->setUniqueIdentifier('unique-id'); // set uinique identifier for each upload (for example user id)
```

to upload tour chunks:

```PHP
$uploadResult = $uploadHandeler->uploadChunk('name-of-chunk-or-chunk-number');
```

And when all chunks upload:
```PHP
$uploadResult = $uploadHandeler
					->setUploadDirectory('path-to-upload-directory') //main directry path to upload (combine chunks here)
                	->finishUpload();
```

## License

hls-video-generater is licensed under the [GPLv3 License](http://opensource.org/licenses/GPL).