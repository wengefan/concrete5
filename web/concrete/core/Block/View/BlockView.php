<?
namespace Concrete\Core\Block\View;
use Concrete\Core\View\AbstractView;
use Loader;
use Environment;
use CacheLocal;
use User;
use Page;
use Block;
use BlockType;
class BlockView extends AbstractView {

	protected $block;
	protected $area;
	protected $blockType;
	protected $blockTypePkgHandle;
	protected $blockViewHeaderFile;
	protected $blockViewFooterFile;
	protected $outputContent = false;
	protected $viewToRender = false;
	protected $viewPerformed = false;

	protected function constructView($mixed) {
		if ($mixed instanceof Block) {
			$this->blockType = $mixed->getBlockTypeObject();
			$this->block = $mixed;
			$this->area = $mixed->getBlockAreaObject();
		} else {
			$this->blockType = $mixed;
			if ($this->blockType->controller) {
				$this->controller = $this->blockType->controller;
			}
		}
		$this->blockTypePkgHandle = $this->blockType->getPackageHandle();
		if (!isset($this->controller)) {
			if (isset($this->block)) {
				$this->controller = $this->block->getInstance();
				$this->controller->setBlockObject($this->block);
			} else {
				$this->controller = $this->blockType->getController();
			}
		}
		
	}		

	public function setAreaObject(Area $area) {
		$this->area = $area;
	}

	public function getAreaObject() {return $this->area;}
	
	public function start($state) {
		if (is_object($this->area)) {
			$this->controller->setAreaObject($this->area);
		}
		/** 
		 * Legacy shit
		 */
		if ($state instanceof Block) {
			$this->block = $state;
			$this->viewToRender = 'view';
		} else {
			$this->viewToRender = $state;
		}
	}

	/**
	 * Creates a URL that can be posted or navigated to that, when done so, will automatically run the corresponding method inside the block's controller.
	 * <code>
	 *     <a href="<?=$this->action('get_results')?>">Get the results</a>
	 * </code>
	 * @param string $task
	 * @return string $url
	 */
	public function action($task) {
		try {
			if (is_object($this->block)) {
				if (is_object($this->block->getProxyBlock())) {
					$b = $this->block->getProxyBlock();
				} else {
					$b = $this->block;
				}
				
				$c = Page::getCurrentPage();
				if (is_object($b) && is_object($c)) {
					$a = $b->getBlockAreaObject();
					return URL::page($c, 'passthru', $a->getAreaHandle(), $b->getBlockID(), $task);
				}
			}
		} catch(Exception $e) {}
	}

	public function startRender() {}
	public function setupRender() {
		$this->runControllerTask();
		if ($this->outputContent) {
			return false;
		}

		$view = $this->viewToRender;

		$env = Environment::get();
		if ($this->viewToRender == 'scrapbook') {
			$scrapbookTemplate = $this->getBlockPath(FILENAME_BLOCK_VIEW_SCRAPBOOK) . '/' . FILENAME_BLOCK_VIEW_SCRAPBOOK;
			if (file_exists($scrapbookTemplate)) {
				$view = 'scrapbook';
			} else {
				$view = 'view';
			}
		}
		if (!in_array($this->viewToRender, array('view', 'add', 'edit', 'scrapbook'))) {
			// then we're trying to render a custom view file, which we'll pass to the bottom functions as $_filename
			$customFilenameToRender = $view . '.php';
			$view = 'view';
		}

		switch($view) {
			case 'view':
				$this->setBlockViewHeaderFile(DIR_FILES_ELEMENTS_CORE . '/block_header_view.php');
				$this->setBlockViewFooterFile(DIR_FILES_ELEMENTS_CORE . '/block_footer_view.php');
				if ($this->controller->blockViewRenderOverride) {
					$template = DIRNAME_BLOCKS . '/' . $this->blockType->getBlockTypeHandle() . '/' . $this->controller->blockViewRenderOverride . '.php';
					$this->setViewTemplate($env->getPath($template, $this->blockTypePkgHandle));
				} else if ($this->block) {
					$bFilename = $this->block->getBlockFilename();
					$bvt = new BlockViewTemplate($this->block);
					if ($bFilename) {
						$bvt->setBlockCustomTemplate($bFilename); // this is PROBABLY already set by the method above, but in the case that it's passed by area we have to set it here
					} else if ($customFilenameToRender) {
						$bvt->setBlockCustomRender($customFilenameToRender); 
					}
					$this->setViewTemplate($bvt->getTemplate());
				} else {
					$template = DIRNAME_BLOCKS . '/' . $this->blockType->getBlockTypeHandle() . '/' . $view . '.php';
					$this->setViewTemplate($env->getPath($template, $this->blockTypePkgHandle));
				}
				break;
			case 'add':
				if ($this->controller->blockViewRenderOverride) {
					$template = DIRNAME_BLOCKS . '/' . $this->blockType->getBlockTypeHandle() . '/' . $this->controller->blockViewRenderOverride . '.php';
				} else {
					$template = DIRNAME_BLOCKS . '/' . $this->blockType->getBlockTypeHandle() . '/' . FILENAME_BLOCK_ADD;
				}
				$this->setViewTemplate($env->getPath($template, $this->blockTypePkgHandle));
				break;
			case 'scrapbook':
				$this->setViewTemplate($env->getPath(DIRNAME_BLOCKS . '/' . $this->blockType->getBlockTypeHandle() . '/' . FILENAME_BLOCK_VIEW_SCRAPBOOK, $this->blockTypePkgHandle));
				break;
			case 'edit':
				if ($this->controller->blockViewRenderOverride) {
					$template = DIRNAME_BLOCKS . '/' . $this->blockType->getBlockTypeHandle() . '/' . $this->controller->blockViewRenderOverride . '.php';
				} else {
					$template = DIRNAME_BLOCKS . '/' . $this->blockType->getBlockTypeHandle() . '/' . FILENAME_BLOCK_EDIT;
				}
				$this->setBlockViewHeaderFile(DIR_FILES_ELEMENTS_CORE . '/block_header_edit.php');
				$this->setBlockViewFooterFile(DIR_FILES_ELEMENTS_CORE . '/block_footer_edit.php');
				$this->setViewTemplate($env->getPath($template, $this->blockTypePkgHandle));
				break;
		}

		$this->viewPerformed = $view;
	}

	protected function onBeforeGetContents() {
		if (in_array($this->viewPerformed, array('scrapbook', 'view'))) {
			$this->controller->runAction('on_page_view', array($this));
			$this->controller->outputAutoHeaderItems();
		}
	}

	public function renderViewContents($scopeItems) {
		if ($this->outputContent) {
			print $this->outputContent;			
		} else {
			extract($scopeItems);
			if ($this->blockViewHeaderFile) {
				include($this->blockViewHeaderFile);
			}

			$this->onBeforeGetContents();
			ob_start();
			include($this->template);
			$this->outputContent = ob_get_contents();
			ob_end_clean();
			print $this->outputContent;
			$this->onAfterGetContents();

			if ($this->blockViewFooterFile) {
				include($this->blockViewFooterFile);
			}
		}
	}

	protected function setBlockViewHeaderFile($file) {
		$this->blockViewHeaderFile = $file;
	}

	protected function setBlockViewFooterFile($file) {
		$this->blockViewFooterFile = $file;
	}

	public function postProcessViewContents($contents) {
		return $contents;
	}

	/**
	 * Returns the path to the current block's directory
	 * @access private
	 * @deprecated
	 * @return string
	*/
	public function getBlockPath($filename = null) {
		$obj = $this->blockType;			
		if (file_exists(DIR_FILES_BLOCK_TYPES . '/' . $obj->getBlockTypeHandle() . '/' . $filename)) {
			$base = DIR_FILES_BLOCK_TYPES . '/' . $obj->getBlockTypeHandle();
		} else if ($obj->getPackageID() > 0) {
			if (is_dir(DIR_PACKAGES . '/' . $obj->getPackageHandle())) {
				$base = DIR_PACKAGES . '/' . $obj->getPackageHandle() . '/' . DIRNAME_BLOCKS . '/' . $obj->getBlockTypeHandle();
			} else {
				$base = DIR_PACKAGES_CORE . '/' . $obj->getPackageHandle() . '/' . DIRNAME_BLOCKS . '/' . $obj->getBlockTypeHandle();
			}
		} else {
			$base = DIR_FILES_BLOCK_TYPES_CORE . '/' . $obj->getBlockTypeHandle();
		}
		return $base;
	}

		/** 
		 * Returns a relative path to the current block's directory. If a filename is specified it will be appended and searched for as well.
		 * @return string
		 */
		public function getBlockURL($filename = null) {
			$obj = $this->block;
			if ($obj->getPackageID() > 0) {
				if (is_dir(DIR_PACKAGES_CORE . '/' . $obj->getPackageHandle())) {
					$base = ASSETS_URL . '/' . DIRNAME_PACKAGES . '/' . $obj->getPackageHandle() . '/' . DIRNAME_BLOCKS . '/' . $obj->getBlockTypeHandle();
				} else {
					$base = DIR_REL . '/' . DIRNAME_PACKAGES . '/' . $obj->getPackageHandle() . '/' . DIRNAME_BLOCKS . '/' . $obj->getBlockTypeHandle();
				}
			} else if (file_exists(DIR_FILES_BLOCK_TYPES . '/' . $obj->getBlockTypeHandle() . '/' . $filename)) {
				$base = DIR_REL . '/' . DIRNAME_BLOCKS . '/' . $obj->getBlockTypeHandle();
			} else {
				$base = ASSETS_URL . '/' . DIRNAME_BLOCKS . '/' . $obj->getBlockTypeHandle();
			}
			
			return $base;
		}
	
	public function inc($file, $args = array()) {
		extract($args);
		extract($this->getScopeItems());
		$env = Environment::get();
		include($env->getPath(DIRNAME_BLOCKS . '/' . $this->blockType->getBlockTypeHandle() . '/' . $file, $this->blockTypePkgHandle));
	}

	public function getScopeItems() {
		$items = parent::getScopeItems();
		$items['b'] = $this->block;
		$items['bt'] = $this->blockType;
		return $items;
	}

	protected function useBlockCache() {
		$u = new User();
		if ($this->viewToRender == 'view' && ENABLE_BLOCK_CACHE && $this->controller->cacheBlockOutput() && ($this->block instanceof Block)) {
			if ((!$u->isRegistered() || ($this->controller->cacheBlockOutputForRegisteredUsers())) &&
				(($_SERVER['REQUEST_METHOD'] != 'POST' || ($this->controller->cacheBlockOutputOnPost() == true)))) {
				return true;
			}
		}
		return false;
	}

	public function finishRender($contents) {
		if ($this->useBlockCache()) {
			$this->block->setBlockCachedOutput($this->outputContent, $this->controller->getBlockTypeCacheOutputLifetime(), $this->area);
		}
		return $contents;
	}

	/** 
	 * Tests the current page controller to see if it's passing through a block action. I really wish
	 * this could be handled in the block controller but due to some dumb decisions years ago it cannot be.
	 */
	protected function getPassThruAction() {
		$c = Page::getCurrentPage();
		$cnt = $c->getController();
		$parameters = $cnt->getParameters();
		$action = $cnt->getAction();
		if ($action == 'passthru' && $this->block->getBlockID() == $parameters[1]) {
			return 'action_' . $parameters[2];
		}
	}


	public function runControllerTask() {
		if ($this->useBlockCache()) {
			$this->outputContent = $this->block->getBlockCachedOutput($this->area);
		}
		
		if (!$this->outputContent) {
			if (in_array($this->viewToRender, array('view', 'add', 'edit', 'composer'))) {
				$method = $this->viewToRender;
			} else {
				$method = 'view';
			}
			if ($method == 'view') {
				$method = $this->getPassThruAction();
				if (!$method) {
					$method = 'view';
				}
			}

			$this->controller->on_start();
			$this->controller->runAction($method, array());
			$this->controller->on_before_render();
		}

	}

	/** 
	 * Legacy
	 * @access private
	 */
	public function getThemePath() {
		$v = View::getInstance();
		return $v->getThemePath();
	}

}
	