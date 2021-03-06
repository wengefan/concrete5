<?php
namespace Concrete\Controller\Backend\File;

use Concrete\Core\Application\EditResponse;
use Concrete\Core\Controller\AbstractController;
use Concrete\Core\File\Filesystem;
use Concrete\Core\File\FolderItemList;
use Concrete\Core\File\Search\ColumnSet\FolderSet;
use Concrete\Core\File\Search\Result\Result;
use Concrete\Core\Tree\Node\Node;
use Concrete\Core\Tree\Node\Type\FileFolder;
use Symfony\Component\HttpFoundation\JsonResponse;

class Folder extends AbstractController
{

    public function add()
    {
        $filesystem = new Filesystem();
        if ($this->request->request->has('currentFolder')) {
            $node = Node::getByID($this->request->request->get('currentFolder'));
            if (is_object($node) && $node instanceof FileFolder) {
                $folder = $node;
            }
        }

        if (!isset($folder)) {
            $folder = $filesystem->getRootFolder();
        }
        $folder = $filesystem->addFolder($folder, $this->request->request->get('folderName'));
        $response = new EditResponse();
        $response->setMessage(t('Folder added.'));
        $response->setAdditionalDataAttribute('folder', $folder);
        $response->outputJSON();
    }


}
