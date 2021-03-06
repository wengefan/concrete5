<?php
namespace Concrete\Controller\Backend;

use Concrete\Core\File\Importer;
use Concrete\Core\Tree\Node\Node;
use Concrete\Core\Tree\Node\Type\FileFolder;
use Concrete\Core\Validation\CSRF\Token;
use Controller;
use FileSet;
use File as ConcreteFile;
use Concrete\Core\File\EditResponse as FileEditResponse;
use Loader;
use FileImporter;
use Exception;
use Permissions as ConcretePermissions;
use FilePermissions;
use Core;

class File extends Controller
{
    public function star()
    {
        $fs = FileSet::createAndGetSet('Starred Files', FileSet::TYPE_STARRED);
        $files = $this->getRequestFiles();
        $r = new FileEditResponse();
        $r->setFiles($files);
        foreach ($files as $f) {
            if ($f->inFileSet($fs)) {
                $fs->removeFileFromSet($f);
                $r->setAdditionalDataAttribute('star', false);
            } else {
                $fs->addFileToSet($f);
                $r->setAdditionalDataAttribute('star', true);
            }
        }
        $r->outputJSON();
    }

    public function rescan()
    {
        $files = $this->getRequestFiles('canEditFileContents');
        $r = new FileEditResponse();
        $r->setFiles($files);
        $successMessage = '';
        $errorMessage = '';
        $successCount = 0;

        foreach ($files as $f) {
            try {
                $fv = $f->getApprovedVersion();
                $resp = $fv->refreshAttributes();
                switch ($resp) {
                    case \Concrete\Core\File\Importer::E_FILE_INVALID:
                        $errorMessage .= t('File %s could not be found.', $fv->getFilename()) . '<br/>';
                        break;
                    default:
                        $successCount++;
                        $successMessage = t2('%s file rescanned successfully.', '%s files rescanned successfully.',
                            $successCount);
                        break;
                }
            } catch (\League\Flysystem\FileNotFoundException $e) {
                $errorMessage .= t('File %s could not be found.', $fv->getFilename()) . '<br/>';
            }
        }
        if ($errorMessage && !$successMessage) {
            $e = \Core::make('error');
            $e->add($errorMessage);
            $r->setError($e);
        } else {
            $r->setMessage($errorMessage . $successMessage);
        }
        $r->outputJSON();
    }

    public function approveVersion()
    {
        $files = $this->getRequestFiles('canEditFileContents');
        $r = new FileEditResponse();
        $r->setFiles($files);
        $fv = $files[0]->getVersion(Loader::helper('security')->sanitizeInt($_REQUEST['fvID']));
        if (is_object($fv)) {
            $fv->approve();
        } else {
            throw new Exception(t('Invalid file version.'));
        }
        $r->outputJSON();
    }

    public function deleteVersion()
    {
        /** @var Token $token */
        $token = $this->app->make('token');
        if (!$token->validate('delete-version')) {
            $files = $this->getRequestFiles('canEditFileContents');
        }
        $r = new FileEditResponse();
        $r->setFiles($files);
        $fv = $files[0]->getVersion(Loader::helper('security')->sanitizeInt($_REQUEST['fvID']));
        if (is_object($fv) && !$fv->isApproved()) {
            if (!$token->validate('version/delete/' . $fv->getFileID() . "/" . $fv->getFileVersionId())) {
                throw new Exception($token->getErrorMessage());
            }
            $fv->delete();
        } else {
            throw new Exception(t('Invalid file version.'));
        }
        $r->outputJSON();
    }

    protected function getRequestFiles($permission = 'canViewFileInFileManager')
    {
        $files = array();
        if (is_array($_REQUEST['fID'])) {
            $fileIDs = $_REQUEST['fID'];
        } else {
            $fileIDs[] = $_REQUEST['fID'];
        }
        foreach ($fileIDs as $fID) {
            $f = ConcreteFile::getByID($fID);
            $fp = new ConcretePermissions($f);
            if ($fp->$permission()) {
                $files[] = $f;
            }
        }

        if (count($files) == 0) {
            Core::make('helper/ajax')->sendError(t('File not found.'));
        }

        return $files;
    }

    protected function handleUpload($property, $index = false)
    {

        if ($index !== false) {
            $name = $_FILES[$property]['name'][$index];
            $tmp_name = $_FILES[$property]['tmp_name'][$index];

            if ($_FILES[$property]['error'][$index]) {
                throw new \Exception(FileImporter::getErrorMessage($_FILES[$property]['error'][$index]));
            }
        } else {

            $name = $_FILES[$property]['name'];
            $tmp_name = $_FILES[$property]['tmp_name'];

            if ($_FILES[$property]['error']) {
                throw new \Exception(FileImporter::getErrorMessage($_FILES[$property]['error']));
            }
        }

        $files = array();
        $fp = FilePermissions::getGlobal();
        $cf = Loader::helper('file');
        if (!$fp->canAddFileType($cf->getExtension($name))) {
            throw new Exception(FileImporter::getErrorMessage(FileImporter::E_FILE_INVALID_EXTENSION));
        } else {
            $folder = null;
            if ($this->request->request->has('currentFolder')) {
                $node = Node::getByID($this->request->request->get('currentFolder'));
                if ($node instanceof FileFolder) {
                    $folder = $node;
                }
            }
            $importer = new FileImporter();
            $response = $importer->import($tmp_name, $name, $folder);
        }
        if (!($response instanceof \Concrete\Core\Entity\File\Version)) {
            throw new Exception(FileImporter::getErrorMessage($response));
        } else {
            $file = $response->getFile();
            if (isset($_POST['ocID'])) {
                // we check $fr because we don't want to set it if we are replacing an existing file
                $file->setOriginalPage($_POST['ocID']);
            }
            $files[] = $file->getJSONObject();
        }
        return $files;
    }

    public function upload()
    {
        $fp = FilePermissions::getGlobal();
        if (!$fp->canAddFiles()) {
            throw new Exception(t("Unable to add files."));
        }

        if ($post_max_size = \Loader::helper('number')->getBytes(ini_get('post_max_size'))) {
            if ($post_max_size < $_SERVER['CONTENT_LENGTH']) {
                throw new Exception(FileImporter::getErrorMessage(Importer::E_FILE_EXCEEDS_POST_MAX_FILE_SIZE));
            }
        }

        if (!Loader::helper('validation/token')->validate()) {
            throw new Exception(Loader::helper('validation/token')->getErrorMessage());
        }

        if (isset($_FILES['file'])){
            $files = $this->handleUpload('file');
        }
        if (isset($_FILES['files']['tmp_name'][0])) {
            $files = array();
            for ($i = 0; $i < count($_FILES['files']['tmp_name']); ++$i) {
                $files = array_merge($files, $this->handleUpload('files', $i));
            }
        }

        Loader::helper('ajax')->sendResult($files);
    }

    public function duplicate()
    {
        $files = $this->getRequestFiles('canCopyFile');
        $r = new FileEditResponse();
        $newFiles = array();
        foreach ($files as $f) {
            $nf = $f->duplicate();
            $newFiles[] = $nf;
        }
        $r->setFiles($newFiles);
        $r->outputJSON();
    }

    public function getJSON()
    {
        $files = $this->getRequestFiles();
        $r = new FileEditResponse();
        $r->setFiles($files);
        $r->outputJSON();
    }
}
