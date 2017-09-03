<?php
namespace MehrdadDadkhah\Video;

class ChunksUploader
{
    private $allowedExtensions       = [];
    private $sizeLimit               = null;
    private $chunksFolder            = 'chunks';
    private $generateUploadDirectory = false;
    private $uniqueIdentifier;
    private $fileName;
    private $fileTotalSize;
    private $inputName;

    protected $uploadName;

    private $mime_types = array(
        'video/x-ms-asf', 'video/x-ms-asf', 'video/x-ms-asf', 'video/x-msvideo',
        'video/x-flv', 'video/x-ivf', 'video/x-la-asf', 'video/mpeg', 'video/quicktime',
        'video/x-sgi-movie', 'video/x-ms-asf', 'video/x-ms-wmp', 'video/x-ms-wmv', 'video/x-ms-wmx',
        'video/mp4', 'video/3gpp', 'video/3gpp2', 'application/vnd.mseq',
        'video/vnd.mpegurl', 'video/x-m4v', 'video/x-f4v', 'video/x-ms-asf',
        'video/webm', 'video/divx', 'video/mkv', 'video/x-matroska', 'application/octet-stream',
    );

    public function uploadChunk(string $chunkName)
    {
        $checkUploadStatus = $this->checkUploadStatus();

        if (!$checkUploadStatus['status']) {
            return $checkUploadStatus;
        }

        if (!$this->makeSubDirectoryForChunks()) {
            return [
                'status' => false,
                'error'  => 'can not make directory for chunks files in path ' . $this->getChunksFolderPath(),
            ];
        }

        if (!is_writable($this->getChunksSubDirectryPath()) && !is_executable($this->getChunksSubDirectryPath())) {
            return [
                'status' => false,
                'error'  => 'Server error. Chunks directory isn not writable or executable.',
            ];
        }

        $uploadChunkPath = $this->getChunksSubDirectryPath() . DIRECTORY_SEPARATOR . $chunkName;
        $upload          = move_uploaded_file($_FILES[$this->inputName]['tmp_name'], $uploadChunkPath);
        chmod($uploadChunkPath, 0775);

        if (!$upload) {
            return [
                'status' => false,
                'error'  => 'can not store chunk file ::: ' . error_get_last()['message'],
            ];
        }

        if (!$this->makeChunkList($chunkName)) {
            return [
                'status' => false,
                'error'  => 'can not store chunk file list ::: ' . error_get_last()['message'],
            ];
        }

        return [
            'status' => true,
        ];
    }

    public function finishUpload()
    {
        $targetPath = $this->getUploadDirectory() . DIRECTORY_SEPARATOR . $this->getUploadName();

        if (!$this->checkAndGenerateOutputDirectory($targetPath)) {
            return [
                'status' => false,
                'error'  => 'can not generate directory path ' . $targetPath,
            ];
        }

        $chunks = $this->getChunkList();

        $targetFile = fopen($targetPath, 'wb');

        foreach ($chunks as $chunkName) {
            $chunkPath = $this->getChunksSubDirectryPath() . DIRECTORY_SEPARATOR . trim($chunkName);

            $chunk = fopen($chunkPath, "rb");
            stream_copy_to_stream($chunk, $targetFile);
            fclose($chunk);
        }

        fclose($targetFile);

        if (!$this->checkMimeType($targetPath)) {
            return [
                'status' => false,
                'error'  => 'not allowed mime type',
            ];
        }

        $deleteExtraFilesStatus = true;
        $deleteExtraFilesError  = null;
        try {

            foreach ($chunks as $chunkName) {
                $chunkPath = $this->getChunksSubDirectryPath() . DIRECTORY_SEPARATOR . trim($chunkName);
                unlink($chunkPath);
            }

            unlink($this->getChunkListFilePath());

            rmdir($this->getChunksSubDirectryPath());
        } catch (\Exception $e) {
            $deleteExtraFilesStatus = false;
            $deleteExtraFilesError  = $e->getMessage() . ' ::: file: ' . $e->getFile() . ' ::: line: ' . $e->getLine();
        }

        return [
            'status'                 => true,
            'uplodedName'            => $this->getUploadName(),
            'uuid'                   => md5(file_get_contents($targetPath)),
            'deleteExtraFilesStatus' => $deleteExtraFilesStatus,
            'deleteExtraFilesError'  => $deleteExtraFilesError,
            'chunksSubDirectryPath'  => $this->getChunksSubDirectryPath(),
        ];
    }

    public function setUploadDirectory(string $path)
    {
        $this->uploadDirectory = $path;

        return $this;
    }

    public function getUploadDirectory(): string
    {
        return $this->uploadDirectory;
    }

    public function setUploadName(string $name)
    {
        $this->uploadName = $name;

        return $this;
    }

    public function getUploadName(): string
    {
        if (empty($this->uploadName)) {
            $this->setUploadName(
                date('Y_m_d') . '_' . md5($this->getMainFileName()) . '.mp4'
            );
        }

        return $this->uploadName;
    }

    private function makeSubDirectoryForChunks(): string
    {
        $path = $this->getChunksSubDirectryPath();

        if (!is_dir($path)) {
            return mkdir($path);
        }

        return true;
    }

    private function makeChunkList(string $chunkName): bool
    {
        $path = $this->getChunkListFilePath();

        $txt    = trim($chunkName) . ',';
        $result = file_put_contents($path, $txt . PHP_EOL, FILE_APPEND | LOCK_EX);

        return ($result > 0 ? true : false);
    }

    private function getChunkList(): array
    {
        $path = $this->getChunkListFilePath();

        $data = file_get_contents($path);
        $data = explode(',', $data);
        unset($data[count($data) - 1]);
        sort($data);

        return $data;
    }

    private function getChunkListFilePath(): string
    {
        return $this->getChunksSubDirectryPath() . DIRECTORY_SEPARATOR . 'chunks_list.php';
    }

    private function getChunksSubDirectryPath(): string
    {
        return $this->getChunksFolderPath() . DIRECTORY_SEPARATOR . $this->getUniqiueName();
    }

    public function setChunksFolderPath(string $path)
    {
        $this->chunksFolder = $path;

        return $this;
    }

    private function getChunksFolderPath(): string
    {
        return $this->chunksFolder;
    }

    public function setUniqueIdentifier(string $indentifier)
    {
        $this->uniqueIdentifier = $indentifier;

        return $this;
    }

    public function setInputName(string $inputName)
    {
        $this->inputName = $inputName;

        return $this;
    }

    private function getUniqiueName(): string
    {
        return $this->uniqueIdentifier . '_' . md5($this->getMainFileName());
    }

    public function setMainFileName(string $fileName)
    {
        $this->fileMainName = $fileName;

        return $this;
    }

    private function getMainFileName()
    {
        return $this->fileMainName;
    }

    public function setFileTotalSize(int $size)
    {
        $this->fileTotalSize = $size;

        return $this;
    }

    private function getFileTotalSize()
    {
        return $this->fileTotalSize;
    }

    private function getFileTempName()
    {
        return $_FILES[$this->inputName]['tmp_name'];
    }

    private function checkUploadStatus(): array
    {
        // Check $_FILES['upfile']['error'] value.
        switch ($_FILES[$this->inputName]['error']) {
            case UPLOAD_ERR_OK:
                return [
                    'status' => true,
                    'error'  => null,
                ];

                break;

            case UPLOAD_ERR_NO_FILE:
                return [
                    'status' => false,
                    'error'  => 'no file sent',
                ];

            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return [
                    'status' => false,
                    'error'  => 'Exceeded filesize limit.',
                ];

            default:
                return [
                    'status' => false,
                    'error'  => 'Unknown errors.',
                ];
        }
    }

    private function checkMimeType(string $videoPath): bool
    {
        umask(002);
        chmod($videoPath, 0755);

        if (!in_array(mime_content_type($videoPath), $this->mime_types)) {
            return false;
        }

        return true;
    }

    public function checkAndGenerateOutputDirectory()
    {
        $this->generateUploadDirectory = true;

        return $this;
    }

    private function checkAndGenerateUploadFolder(string $path)
    {
        if ($this->generateUploadDirectory && !is_dir($path)) {
            $dirWay = explode('/', $path);

            $dir = '';
            foreach ($dirWay as $step => $directory) {
                if ($step > 0) {
                    $dir .= '/' . $directory;

                    if (!is_dir($dir)) {
                        mkdir($dir, 0775);
                    }
                }
            }
        }

        return true;
    }
}
