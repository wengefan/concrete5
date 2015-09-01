<?php
namespace Concrete\Controller\SinglePage\Dashboard\Pages;

use Core;
use \Concrete\Core\Page\Controller\DashboardPageController;
use Database;
use PageTemplate;
use Exception;

class Templates extends DashboardPageController
{

    public function view()
    {
        $this->set('templates', PageTemplate::getList());
    }

    public function delete($pTemplateID, $token = '')
    {
        $db = Database::connection();
        $valt = Core::make('helper/validation/token');
        if (!$valt->validate('delete_page_template', $token)) {
            $this->set('message', $valt->getErrorMessage());
        } else {
            $pt = PageTemplate::getByID($pTemplateID);
            $pt->delete();
            $this->redirect("/dashboard/pages/templates", "page_template_deleted");
        }
    }

    public function edit($pTemplateID = false)
    {
        $this->set("icons", PageTemplate::getIcons());
        $template = PageTemplate::getByID($pTemplateID);
        if (!is_object($template)) {
            throw new Exception(t('Invalid page template'));
        }
        $this->set('template', $template);
    }

    public function page_template_added()
    {
        $this->set('success', t('Page template added successfully.'));
        $this->view();
    }

    public function page_template_deleted()
    {
        $this->set('success', t('Page template deleted successfully.'));
        $this->view();
    }

    public function page_template_updated()
    {
        $this->set('success', t('Page template updated successfully.'));
        $this->view();
    }

    public function update()
    {
        $valt = Core::make('helper/validation/token');
        $pt = PageTemplate::getByID($_REQUEST['pTemplateID']);
        $pTemplateName = $_POST['pTemplateName'];
        $pTemplateHandle = $_POST['pTemplateHandle'];
        $pTemplateIcon = $_POST['pTemplateIcon'];
        $vs = Core::make('helper/validation/strings');

        if (!is_object($pt)) {
            $this->error->add(t('Invalid page template object.'));
        }

        if (!$pTemplateHandle) {
            $this->error->add(t("Handle required."));
        } elseif (!$vs->handle($pTemplateHandle)) {
            $this->error->add(t('Handles must contain only letters, numbers or the underscore symbol.'));
        }

        if (!$pTemplateName) {
            $this->error->add(t("Name required."));
        } elseif (preg_match('/[<>;{}?"`]/i', $pTemplateName)) {
            $this->error->add(t('Invalid characters in page template name.'));
        }

        if (!$valt->validate('update_page_template')) {
            $this->error->add($valt->getErrorMessage());
        }

        if (!$this->error->has()) {
            $pt->update($pTemplateHandle, $pTemplateName, $pTemplateIcon);
            $this->redirect('/dashboard/pages/templates', 'page_template_updated');
        }   

        $this->edit($this->post('pTemplateID'));
    }

}