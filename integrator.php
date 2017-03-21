<?php 

if (!defined('_PS_VERSION_'))
	exit;

class integrator extends Module
{
	
	public function __construct()
	{		
		$this->name = 'integrator';
		$this->tab = 'pricing_promotion';
		$this->version = '5.3.1';
		$this->author = 'PrestaHelp';
		$this->need_instance = 0;
		$this->ps_versions_compliancy = array('min' => '1.5.0.1', 'max' => '1.7.1');
		parent::__construct();
		$this->displayName = $this->l('Integrator');
		$this->description = $this->l('Modul generujacy pliki XML do najpopularniejszych porównywarek cenowych, takich jak: Ceneo, Skapiec czy Nokaut.');
		$this->confirmUninstall = 'Czy na pewno chcesz usunąć ten moduł?';
		if( _PS_VERSION_ > '1.6.0.0')
			$this->bootstrap = true;
	}
	
	public function install()
	{
		$this->instal_config();
		$this->create_table();
		$this->setCeneoCategory();
		if(!parent::install() OR !$this->registerHook('displayAdminProductsExtra') OR !$this->registerHook('actionProductUpdate') )
			return false;
		else 
			return true;
	}
	
	private function instal_config()
	{
		Configuration::updateValue('ITG_PRODUCT_0', 1);
		Configuration::updateValue('ITG_IMAGE_DEFAULT', 6);
		Configuration::updateValue('ITG_PRICE', 1);
		Configuration::updateValue('ITG_FREESHIPPING_NOKAUT', 0);
		Configuration::updateValue('ITG_PRODUCTQUANTITY_0_CENEO', 99);
		Configuration::updateValue('ITG_PRODUCTQUANTITY_0_NOKAUT', 4);
		Configuration::updateValue('ITG_PRODUCT_DESCRIPTION', 1);
		Configuration::updateValue('ITG_PRODUCT_DESCRIPTION2', 1);
		Configuration::updateValue('ITG_PRODUCT_DESCRIPTION3', 1);
		Configuration::updateValue('ITG_PRODUCT_DESCRIPTION_COUNT', 200);
		Configuration::updateValue('ITG_PRODUCT_IMAGE', 1);
		Configuration::updateValue('ITG_PRODUCT_CENEO', 1);
		Configuration::updateValue('ITG_PRODUCT_NOKAUT', 1);
		Configuration::updateValue('ITG_PRODUCT_SKAPIEC', 1);
		Configuration::updateValue('ITG_ALLPRODUCT_CENEO', 0);
		Configuration::updateValue('ITG_CATEGORY_DEFAULT', 40);
		Configuration::updateValue('ITG_TITLE_CENEO', '');
		Configuration::updateValue('ITG_TITLE_POSITION_CENEO', 2);
		
	}
	
	private function create_table()
	{
		Db::getInstance()->execute('
			CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'itg_categories_ceneo` (
				`id_ceneo` int(10) NOT NULL,
				`name` varchar(255) NOT NULL,
				`parent_id` int(10) NOT NULL,
				PRIMARY KEY (`id_ceneo`)
			) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=UTF8;
		');		
		Db::getInstance()->execute('
			CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'itg_categories_ceneo_mapping` (
				`id_mapping` int(10) unsigned NOT NULL AUTO_INCREMENT,
				`id_ceneo` int(10) unsigned NOT NULL,
				`id_shop` int(10) unsigned NOT NULL,
				PRIMARY KEY (`id_mapping`)
			) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=UTF8;
		');
		Db::getInstance()->execute('
			CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'itg_categories_nokaut_mapping` (
				`id_mapping` int(10) unsigned NOT NULL AUTO_INCREMENT,
				`id_nokaut` int(10) unsigned NULL,
				`id_shop` int(10) unsigned NOT NULL,
				PRIMARY KEY (`id_mapping`)
			) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=UTF8;
		');
		Db::getInstance()->execute('
			CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'itg_categories_skapiec_mapping` (
				`id_mapping` int(10) unsigned NOT NULL AUTO_INCREMENT,
				`id_skapiec` int(10) unsigned NULL,
				`id_shop` int(10) unsigned NOT NULL,
				PRIMARY KEY (`id_mapping`)
			) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=UTF8;
		');
		Db::getInstance()->execute('
			CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'itg_products_ceneo` (
				`id_product` int(10) unsigned NOT NULL,
				`name` varchar(255) NULL,
				`delay` int(10) unsigned NOT NULL,
				`basket` int(1) unsigned NOT NULL,
				`description` TEXT NULL,
				PRIMARY KEY (`id_product`)
			) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=UTF8;
		');
		Db::getInstance()->execute('
			CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'itg_products_nokaut` (
				`id_product` int(10) unsigned NOT NULL,
				`name` varchar(255) NULL,
				`delay` int(10) unsigned NOT NULL,
				`description` TEXT NULL,
				PRIMARY KEY (`id_product`)
			) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=UTF8;
		');
		Db::getInstance()->execute('
			CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'itg_products_skapiec` (
				`id_product` int(10) unsigned NOT NULL,
				`name` varchar(255) NULL,
				`delay` int(10) unsigned NOT NULL,
				`description` TEXT NULL,
				PRIMARY KEY (`id_product`)
			) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=UTF8;
		');
	}
	
	public function uninstall()
	{
		if ( !parent::uninstall() && !$this->uninstalTable())
			return false;
		return true;
	}
	
	public function uninstalTable()
	{
		if( Db::getInstance()->execute('DROP TABLE '._DB_PREFIX_.'itg_products_ceneo') ){
			return true;
		}
		return false;
	}
	
	private function setCeneoCategory()
	{
		$tab = $this->addCeneoCategory();
		
		if( !empty($tab) )
		{
			foreach($tab as $t)
			{
				Db::getInstance()->execute('INSERT INTO '._DB_PREFIX_.'itg_categories_ceneo(id_ceneo,name,parent_id) VALUES("'.$t['id'].'","'.$t['name'].'","'.$t['id_parent'].'")');
			}
		}
		else
			Tools::displayError('Błąd w pobieraniu kategorii z CENEO');
	}
	
	public function addCeneoCategory()
	{
		define('XML_DIR','http://api.ceneo.pl/Kategorie/dane.xml');
		$xml_file = file_get_contents(XML_DIR);
		$array = json_decode(json_encode((array)simplexml_load_string($xml_file)),1);
		$i = 0;
		foreach($array['Category'] as $a)
		{
			$tab[$i]['name'] = $a['Name'];
			$tab[$i]['id'] = $a['Id'];
			$tab[$i]['id_parent'] = 0;
			$i++;
			if( !empty($a['Subcategories']['Category']) )
			{
				$i1 = 0;
				foreach($a['Subcategories']['Category'] as $as)
				{
					$tab[$i]['name'] = $as['Name'];
					$tab[$i]['id'] = $as['Id'];
					$tab[$i]['id_parent'] = $a['Id'];
					$i++;
					if( !empty($as['Subcategories']['Category'][0]))
					{
						$i2 = 0;
						foreach($as['Subcategories']['Category'] as $ass)
						{
							$tab[$i]['name'] = $ass['Name'];
							$tab[$i]['id'] = $ass['Id'];
							$tab[$i]['id_parent'] = $as['Id'];
							$i++;
							if( !empty($ass['Subcategories']['Category']) )
							{
								$i3 = 0;
								foreach($ass['Subcategories']['Category'] as $asss)
								{
									$tab[$i]['name'] = $asss['Name'];
									$tab[$i]['id'] = $asss['Id'];
									$tab[$i]['id_parent'] = $ass['Id'];
									$i++;
									if( !empty($asss['Subcategories']['Category']) )
									{
										$i4 = 0;
										foreach($asss['Subcategories']['Category'] as $assss)
										{
											$tab[$i]['name'] = $assss['Name'];
											$tab[$i]['id'] = $assss['Id'];
											$tab[$i]['id_parent'] = $asss['Id'];
											$i4++;
											$i++;
										}
									}
									$i3++;
// 									$i++;
								}
							}
							$i2++;
// 							$i++;
						}
					}
					$i1++;
// 					$i++;
				}
			}
// 			$i++;
		}
		return $tab;
	}
	
	/*public function uninstall() 
	{
		Db::getInstance()->execute('DROP TABLE IF EXISTS `'._DB_PREFIX_.'itg_categories_ceneo`;');
		if(!parent::uninstall())
			return false;
		else return true;
	}*/
	
	public function getContent()
	{
		$this->post();
		
		$select_ceneo = array();
		$select_ceneo = Db::getInstance()->executeS('SELECT id_shop as id_category FROM '._DB_PREFIX_.'itg_categories_ceneo_mapping');
		
		if( _PS_VERSION_ < '1.6')
		{		
			$trads = array(
				'Home' => $this->l('Strona główna'),
				'selected' => $this->l('selected'),
				'Collapse All' => $this->l('Rozwiń'),
				'Expand All' => $this->l('Zwiń'),
				'Check All' => $this->l('Zaznacz wszystkie'),
				'Uncheck All'  => $this->l('Odznacz wszystkie')
			);
			$helper = new Helper();			
			$helper_category_ceneo = $helper->renderCategoryTree(null,$select_ceneo);
		}
		else
		{		
			$select = array();	
			if( !empty($select_ceneo) )
			{
				foreach($select_ceneo as $sc)
				{
					$select[] = $sc['id_category'];
				}
			}
			$categories = new HelperTreeCategories('associated-categories-tree');
			$categories->setUseCheckBox(1)->setUseSearch(1)->setSelectedCategories($select);
			$helper_category_ceneo = $categories->render();
		}
		$ceneo = Db::getInstance()->executeS('SELECT * FROM '._DB_PREFIX_.'itg_categories_ceneo WHERE parent_id = 0');
		
		$exist_ceneo = 0;
		$exist_skapiec = 0;
		$exist_nokaut = 0;
		
		$dir_ceneo = dirname(__FILE__).'/../../itg-ceneo.xml';
		$dir_nokaut = dirname(__FILE__).'/../../itg-nokaut.xml';
		$dir_skapiec = dirname(__FILE__).'/../../itg-skapiec.xml';
		
		$fn_c = '';
		$fn_s = '';
		$fn_n = '';
		
		if( file_exists($dir_ceneo) ) 
		{
			$exist_ceneo = 1;
			$fn_c = date("Y-m-d H:i:s.",filemtime($dir_ceneo));
		}
		if( file_exists($dir_skapiec) ) {
			$exist_skapiec = 1;
			$fn_s = date("Y-m-d H:i:s.",filemtime($dir_skapiec));
		}
		if( file_exists($dir_nokaut) ) {
			$exist_nokaut = 1;
			$fn_n = date("Y-m-d H:i:s.",filemtime($dir_nokaut));
		}
		
		$shop_protocol = Tools::getShopProtocol();
		if( $shop_protocol == 'https://')
		{
			$dir_js = Tools::getProtocol(true).''.Tools::getShopDomain().'/js/';
			$base_dir = Tools::getProtocol(true).''.Tools::getShopDomain().'/';
		}
		else
		{
			$dir_js = Tools::getProtocol().''.Tools::getShopDomain().'/js/';
			$base_dir = Tools::getProtocol().''.Tools::getShopDomain().'/';
		}
		
		$this->context->smarty->assign(array(
			'href' => AdminController::$currentIndex.'&configure='.$this->name.'&token='.Tools::getAdminTokenLite('AdminModules'),
			'js' => $dir_js,
			'base' => $base_dir,
			'stan0' => Configuration::get('ITG_PRODUCT_0'),
			'imageType' => ImageType::getImagesTypes('products'),
			'imgTyp' => Configuration::get('ITG_IMAGE_DEFAULT'),
			'itPrice' => Configuration::get('ITG_PRICE'),
			'itdescription' => Configuration::get('ITG_PRODUCT_DESCRIPTION'),
			'itdescription2' => Configuration::get('ITG_PRODUCT_DESCRIPTION2'),
			'itdescription3' => Configuration::get('ITG_PRODUCT_DESCRIPTION3'),
			'description_count' => Configuration::get('ITG_PRODUCT_DESCRIPTION_COUNT'),
			'freenokaut' => Configuration::get('ITG_FREESHIPPING_NOKAUT'),
			'stanCeneo' => Configuration::get('ITG_PRODUCTQUANTITY_0_CENEO'),
			'stanNokaut' => Configuration::get('ITG_PRODUCTQUANTITY_0_NOKAUT'),
			'pCeneo' => Configuration::get('ITG_PRODUCT_CENEO'),
			'pNokaut' => Configuration::get('ITG_PRODUCT_NOKAUT'),
			'pSkapiec' => Configuration::get('ITG_PRODUCT_SKAPIEC'),
			'stanCeneoAll' => Configuration::get('ITG_ALLPRODUCT_CENEO'),
			'ceneoDefault' => Configuration::get('ITG_CATEGORY_DEFAULT'),
			'ceneoDefaultName' => $this->getDirCeneoDefault(Configuration::get('ITG_CATEGORY_DEFAULT')),
			'helperCeneo' => $helper_category_ceneo,
			'ceneo_selected' => $this->goCategory(Db::getInstance()->executeS('SELECT * FROM '._DB_PREFIX_.'itg_categories_ceneo_mapping')),
			'ceneo_category' => $ceneo,
			'exist_ceneo' => $exist_ceneo,
			'exist_skapiec' => $exist_skapiec,
			'exist_nokaut' => $exist_nokaut,
			'itimage' => Configuration::get('ITG_PRODUCT_IMAGE'),
			'fnc' => $fn_c,
			'fns' => $fn_s,
			'fnn' => $fn_n,
		    'ceneotitleposition' => Configuration::get('ITG_TITLE_POSITION_CENEO'),
		    'ceneotitle' => Configuration::get('ITG_TITLE_CENEO')
		));
		if( _PS_VERSION_ < '1.6')
			return $this->display(__FILE__,'views/templates/admin/integrator.tpl');
		else if(_PS_VERSION_ <= '1.6.0.6')
			return $this->display(__FILE__,'views/templates/admin/integrator_1606.tpl');
		else if(_PS_VERSION_ < '1.6.0.14')
			return $this->display(__FILE__,'views/templates/admin/integrator_16.tpl');
		else if(_PS_VERSION_ < '1.6.1.0')
			return $this->display(__FILE__,'views/templates/admin/integrator_1614.tpl');
		else
			return $this->display(__FILE__,'views/templates/admin/integrator_1610.tpl');
	}
	
	public function post()
	{
		if( Tools::isSubmit('saveUstawienia') )
			$this->saveUstawienia();
		if( Tools::isSubmit('saveCeneo') )
			$this->saveCeneo();
		if( Tools::isSubmit('saveCeneoMap') )
			$this->saveCeneoMap();
		if( Tools::getValue('generuj') )
			$this->generuj(Tools::getValue('generuj'));
	}
	
	private function saveUstawienia()
	{
		if( Tools::getValue('stan0') ) Configuration::updateValue('ITG_PRODUCT_0',Tools::getValue('stan0'));
		if( Tools::getValue('defaultImage') ) Configuration::updateValue('ITG_IMAGE_DEFAULT',Tools::getValue('defaultImage'));
		if( Tools::getValue('itPrice') ) Configuration::updateValue('ITG_PRICE',Tools::getValue('itPrice'));
		if( Tools::getValue('itdescription') ) Configuration::updateValue('ITG_PRODUCT_DESCRIPTION',Tools::getValue('itdescription'));
		if( Tools::getValue('itdescription2') ) Configuration::updateValue('ITG_PRODUCT_DESCRIPTION2',Tools::getValue('itdescription2'));
		if( Tools::getValue('itdescription3') ) Configuration::updateValue('ITG_PRODUCT_DESCRIPTION3',Tools::getValue('itdescription3'));
		if( Tools::getValue('itimage') ) Configuration::updateValue('ITG_PRODUCT_IMAGE',Tools::getValue('itimage'));
		if( Tools::getValue('descriptionCount') ) Configuration::updateValue('ITG_PRODUCT_DESCRIPTION_COUNT',Tools::getValue('descriptionCount'));
		if( Tools::getValue('freenokaut') ) Configuration::updateValue('ITG_FREESHIPPING_NOKAUT',Tools::getValue('freenokaut'));
		if( Tools::getValue('stanCeneo') ) Configuration::updateValue('ITG_PRODUCTQUANTITY_0_CENEO',Tools::getValue('stanCeneo'));
		if( Tools::getValue('stanNokaut') ) Configuration::updateValue('ITG_PRODUCTQUANTITY_0_NOKAUT',Tools::getValue('stanNokaut'));
		if( Tools::getValue('pCeneo') ) Configuration::updateValue('ITG_PRODUCT_CENEO',Tools::getValue('pCeneo'));
		if( Tools::getValue('pNokaut') ) Configuration::updateValue('ITG_PRODUCT_NOKAUT',Tools::getValue('pNokaut'));
		if( Tools::getValue('pSkapiec') ) Configuration::updateValue('ITG_PRODUCT_SKAPIEC',Tools::getValue('pSkapiec'));
		if( Tools::getValue('stanCeneoAll') ) Configuration::updateValue('ITG_ALLPRODUCT_CENEO',Tools::getValue('stanCeneoAll'));
		if( Tools::getValue('ceneoDefault') ) Configuration::updateValue('ITG_CATEGORY_DEFAULT',Tools::getValue('ceneoDefault'));
		
		if (Tools::getValue('ceneotitle')) Configuration::updateValue('ITG_TITLE_CENEO', Tools::getValue('ceneotitle'));
		if (Tools::getValue('ceneotitleposition')) Configuration::updateValue('ITG_TITLE_POSITION_CENEO', Tools::getValue('ceneotitleposition'));
		
		header('Location: '.AdminController::$currentIndex.'&configure='.$this->name.'&token='.Tools::getAdminTokenLite('AdminModules'));
	}
	
	private function saveCeneo()
	{
		$category = Tools::getValue('categoryBox');
		$selected = Db::getInstance()->executeS('SELECT * FROM '._DB_PREFIX_.'itg_categories_ceneo_mapping');
		
		if( empty($selected) )
		{
			if( !empty($category) )
			{
				foreach($category as $c)
				{
					Db::getInstance()->execute('INSERT INTO '._DB_PREFIX_.'itg_categories_ceneo_mapping(id_shop) VALUES('.(int)$c.')');
				}
			}
			header('Location: '.AdminController::$currentIndex.'&configure='.$this->name.'&token='.Tools::getAdminTokenLite('AdminModules'));
		}
		else
		{
			foreach($selected as $s)
			{
				if( !in_array($s['id_shop'],$category) )
				{
					Db::getInstance()->execute('DELETE FROM '._DB_PREFIX_.'itg_categories_ceneo_mapping WHERE id_shop = '.(int)$s['id_shop']);
				}
			}
			
			if( !empty($category) )
			{
				foreach($category as $c)
				{
					$is = Db::getInstance()->getValue('SELECT id_shop FROM '._DB_PREFIX_.'itg_categories_ceneo_mapping WHERE id_shop = '.(int)$c);
					if( empty($is) )
						Db::getInstance()->execute('INSERT INTO '._DB_PREFIX_.'itg_categories_ceneo_mapping(id_shop) VALUES('.(int)$c.')');
				}
			}	
			header('Location: '.AdminController::$currentIndex.'&configure='.$this->name.'&token='.Tools::getAdminTokenLite('AdminModules'));
		}
	}

	private function saveCeneoMap()
	{
		if( !empty($_POST['cf']) )
		{
			foreach ($_POST['cf'] as $key => $value)
			{
				//print_r($value); echo ' | ';
				$count = 0;
		
				if(is_numeric($value[0]))
				{					
					$count = count($value);
					$id_shop_category = $key;
					if( $count > 3 )
					{
						if(is_numeric($value[$count-1])) $id_ceneo_category = $value[$count-1];
						if(is_numeric($value[$count-2])) $id_ceneo_category = $value[$count-2];
						if(is_numeric($value[$count-3])) $id_ceneo_category = $value[$count-3];
						if(is_numeric($value[$count-4])) $id_ceneo_category = $value[$count-4];
					}
					if( $count > 2 )
					{
						if(is_numeric($value[$count-1])) $id_ceneo_category = $value[$count-1];
						if(is_numeric($value[$count-2])) $id_ceneo_category = $value[$count-2];
						if(is_numeric($value[$count-3])) $id_ceneo_category = $value[$count-3];
					}
					if( $count > 1 )
					{
						if(is_numeric($value[$count-1])) $id_ceneo_category = $value[$count-1];
						if(is_numeric($value[$count-2])) $id_ceneo_category = $value[$count-2];
					}
					if( $count > 0 )
						if(is_numeric($value[$count-1])) $id_ceneo_category = $value[$count-1];
					
					//print($id_ceneo_category.' ');
					
					Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'itg_categories_ceneo_mapping` SET id_ceneo = '.$id_ceneo_category.' WHERE id_shop = '.$id_shop_category);
				}		
			}
		}
	}
	
	public function goCategory($category)
	{
		$tab = array();
		foreach($category as $key => $c)
		{
			$ctg = new Category($c['id_shop'],$this->context->cookie->id_lang);
			$ceneo = Db::getInstance()->getRow('SELECT * FROM '._DB_PREFIX_.'itg_categories_ceneo WHERE id_ceneo = '.(int)$c['id_ceneo']);
			$ceneo_before = Db::getInstance()->getRow('SELECT * FROM '._DB_PREFIX_.'itg_categories_ceneo WHERE id_ceneo = '.(int)$ceneo['parent_id']);
			$ceneo_before2 = Db::getInstance()->getRow('SELECT * FROM '._DB_PREFIX_.'itg_categories_ceneo WHERE id_ceneo = '.(int)$ceneo_before['parent_id']);
			$ceneo_before3 = Db::getInstance()->getRow('SELECT * FROM '._DB_PREFIX_.'itg_categories_ceneo WHERE id_ceneo = '.(int)$ceneo_before2['parent_id']);
			$name = '';
			
			if(	$c['id_ceneo'] > 0 )
			{	
				if( empty($ceneo_before) )
					$name = $ceneo['name'];
				
				if( !empty($ceneo_before) && $ceneo['parent_id'] > 0)
					$name = $ceneo_before['name'].' / '.$ceneo['name'];
				
				if( !empty($ceneo_before2['name']) && $ceneo['parent_id'] > 0 && $ceneo_before['parent_id'] > 0)
					$name = $ceneo_before2['name'].' / '.$ceneo_before['name'].' / '.$ceneo['name'];
				
				if( !empty($ceneo_before3['name']) && $ceneo['parent_id'] > 0 && $ceneo_before['parent_id'] > 0 && $ceneo_before2['parent_id'] > 0)
					$name = $ceneo_before3['name'].' / '.$ceneo_before2['name'].' / '.$ceneo_before['name'].' / '.$ceneo['name'];
			}	
			$tab[$key]['shop_name'] = $ctg->name;
			$tab[$key]['ceneo_name'] = $name;
			$tab[$key]['id_shop'] = $c['id_shop']; 
		}
		return $tab;
	}
	
	private function getDirCeneoDefault($category)
	{
	    $name = '';
	    $ceneo = Db::getInstance()->getRow('SELECT * FROM '._DB_PREFIX_.'itg_categories_ceneo WHERE id_ceneo = '.(int)$category);
	    if (!empty($ceneo)) {
	        if ($ceneo['parent_id'] > 0) {
	            $ceneo2 = Db::getInstance()->getRow('SELECT * FROM '._DB_PREFIX_.'itg_categories_ceneo WHERE id_ceneo = '.(int)$ceneo['parent_id']); 
	            if (!empty($ceneo2)) {
	                if ($ceneo2['parent_id'] > 0) {
	                    $ceneo3 = Db::getInstance()->getRow('SELECT * FROM '._DB_PREFIX_.'itg_categories_ceneo WHERE id_ceneo = '.(int)$ceneo2['parent_id']);
	                    if (!empty($ceneo3)) {
	                        if ($ceneo3['parent_id'] > 0) {
	                            $ceneo4 = Db::getInstance()->getRow('SELECT * FROM '._DB_PREFIX_.'itg_categories_ceneo WHERE id_ceneo = '.(int)$ceneo3['parent_id']);
	                            if (!empty($ceneo4)) {
	                                if ($ceneo4['parent_id'] > 0) {
	                                    $ceneo5 = Db::getInstance()->getRow('SELECT * FROM '._DB_PREFIX_.'itg_categories_ceneo WHERE id_ceneo = '.(int)$ceneo4['parent_id']);
	                                    if (!empty($ceneo5)) {
	                                        if ($ceneo5['parent_id'] > 0) {
	                                            
	                                        } else {
	                                            $name .= '/'.$ceneo['name'].'/'.$ceneo2['name'].'/'.$ceneo3['name'].'/'.$ceneo4['name'].'/'.$ceneo5['name'];
	                                        }
	                                    } else {
	                                        $name .= '/'.$ceneo['name'].'/'.$ceneo2['name'].'/'.$ceneo3['name'].'/'.$ceneo4['name'];	                                        
	                                    }
	                                } else {
	                                    $name .= '/'.$ceneo['name'].'/'.$ceneo2['name'].'/'.$ceneo3['name'].'/'.$ceneo4['name'];
	                                }
	                            } else {
	                                $name .= '/'.$ceneo['name'].'/'.$ceneo2['name'].'/'.$ceneo3['name'];
	                            }
	                        } else {
	                            $name .= '/'.$ceneo['name'].'/'.$ceneo2['name'].'/'.$ceneo3['name'];
	                        }
	                    } else {
	                       $name .= '/'.$ceneo['name'].'/'.$ceneo2['name'];
	                    }
	                } else {
	                    $name .= '/'.$ceneo['name'].'/'.$ceneo2['name'];
	                }
	            } else {
	                $name .= '/'.$ceneo['name'];
	            }
	        } else {
	            $name .= '/'.$ceneo['name'];
	        }
	    }
	    return $name;
	}
	
	public function hookDisplayAdminProductsExtra($params)
	{
		global $cookie;
		$id_product = Tools::getValue('id_product');
		$product = new Product($id_product, $cookie->id_lang);
		$ceneo_p = Db::getInstance()->getRow('SELECT * FROM '._DB_PREFIX_.'itg_products_ceneo WHERE id_product = '.(int)$id_product);
		$nokaut_p = Db::getInstance()->getRow('SELECT * FROM '._DB_PREFIX_.'itg_products_nokaut WHERE id_product = '.(int)$id_product);
		$skapiec_p = Db::getInstance()->getRow('SELECT * FROM '._DB_PREFIX_.'itg_products_skapiec WHERE id_product = '.(int)$id_product);
		
		$this->context->smarty->assign(array(
			'ceneo' => !empty($ceneo_p) ? 1 : 0,
			'skapiec' => !empty($skapiec_p) ? 1 : 0,
			'nokaut' => !empty($nokaut_p) ? 1 : 0,
			'ceneo_text' => $ceneo_p['name'],
			'skapiec_text' => $skapiec_p['name'],
			'nokaut_text' => $nokaut_p['name'],
			'stanCeneo' => $ceneo_p['delay'],
			'stanSkapiec' => $skapiec_p['delay'],
			'stanNokaut' => $nokaut_p['delay'],
			'ceneoBasket' => $ceneo_p['basket'],
			'ceneoDesc' => $ceneo_p['description'],
			'skapiecDesc' => $skapiec_p['description'],
			'nokautDesc' => $nokaut_p['description'],
		));
		if(_PS_VERSION_ < '1.6.0.1')
			return $this->display(__FILE__, 'product.tpl');
		else
			return $this->display(__FILE__, 'product1610.tpl');
	}
	
	public function hookActionProductUpdate($params)
	{
		global $cookie;
		$id_product = Tools::getValue('id_product');
		$ceneo = $_POST['integrator']['ceneo'];
		$skapiec = $_POST['integrator']['skapiec'];
		$nokaut = $_POST['integrator']['nokaut'];
		
		$ceneo_p = Db::getInstance()->getRow('SELECT * FROM '._DB_PREFIX_.'itg_products_ceneo WHERE id_product = '.(int)$id_product);
		$nokaut_p = Db::getInstance()->getRow('SELECT * FROM '._DB_PREFIX_.'itg_products_nokaut WHERE id_product = '.(int)$id_product);
		$skapiec_p = Db::getInstance()->getRow('SELECT * FROM '._DB_PREFIX_.'itg_products_skapiec WHERE id_product = '.(int)$id_product);
				
		if( $ceneo == 1 )
		{
			if( empty($ceneo_p) )
				Db::getInstance()->execute('INSERT INTO '._DB_PREFIX_.'itg_products_ceneo(id_product,name,delay,basket,description) VALUES("'.$id_product.'","'.$_POST['integrator']['ceneo_text'].'","'.$_POST['integrator']['stanCeneo'].'","'.$_POST['integrator']['ceneo_basket'].'","'.$_POST['integrator']['ceneo_desc'].'") ');
			else 
				Db::getInstance()->execute('UPDATE '._DB_PREFIX_.'itg_products_ceneo SET name = "'.$_POST['integrator']['ceneo_text'].'", delay = "'.$_POST['integrator']['stanCeneo'].'", basket = "'.$_POST['integrator']['ceneo_basket'].'", description = "'.$_POST['integrator']['ceneo_desc'].'" WHERE id_product = '.$id_product);
		}
		else 
			if( !empty($ceneo_p) )
				Db::getInstance()->execute('DELETE FROM '._DB_PREFIX_.'itg_products_ceneo WHERE id_product = '.$id_product);
			
		if( $nokaut == 1 )
		{
			if( empty($nokaut_p) )
				Db::getInstance()->execute('INSERT INTO '._DB_PREFIX_.'itg_products_nokaut(id_product,name,delay,description) VALUES("'.$id_product.'","'.$_POST['integrator']['nokaut_text'].'","'.$_POST['integrator']['stanNokaut'].'","'.$_POST['integrator']['nokaut_desc'].'") ');
			else
				Db::getInstance()->execute('UPDATE '._DB_PREFIX_.'itg_products_nokaut SET name = "'.$_POST['integrator']['nokaut_text'].'", delay = "'.$_POST['integrator']['stanNokaut'].'", description = "'.$_POST['integrator']['nokaut_desc'].'" WHERE id_product = '.$id_product);
		}
		else
			if( !empty($nokaut_p) )
				Db::getInstance()->execute('DELETE FROM '._DB_PREFIX_.'itg_products_nokaut WHERE id_product = '.$id_product);
		
		if( $skapiec == 1 )
		{
			if( empty($skapiec_p) )
				Db::getInstance()->execute('INSERT INTO '._DB_PREFIX_.'itg_products_skapiec(id_product,name,description) VALUES("'.$id_product.'","'.$_POST['integrator']['skapiec_text'].'","'.$_POST['integrator']['skapiec_desc'].'" ) ');
			else
				Db::getInstance()->execute('UPDATE '._DB_PREFIX_.'itg_products_skapiec SET name = "'.$_POST['integrator']['skapiec_text'].'", description = "'.$_POST['integrator']['skapiec_desc'].'" WHERE id_product = '.$id_product);
		}
		else
			if( !empty($skapiec_p) )
				Db::getInstance()->execute('DELETE FROM '._DB_PREFIX_.'itg_products_skapiec WHERE id_product = '.$id_product);
	}
	
	public function generuj($name)
	{
		switch ($name) {
			case 'Ceneo':$this->generujCeneo();break;
			case 'Nokaut':$this->generujNokaut();break;
			case 'Skapiec':$this->generujSkapiec();break;
		}
	}
	
	public function generujCeneo()
	{
		$product_config = Configuration::get('ITG_PRODUCT_CENEO');
		$product_stan = Configuration::get('ITG_PRODUCT_0');
		$product_image = Configuration::get('ITG_IMAGE_DEFAULT');
		$product_price = Configuration::get('ITG_PRICE');
		$product_delay = Configuration::get('ITG_PRODUCTQUANTITY_0_CENEO');
		$product_delay_all = Configuration::get('ITG_ALLPRODUCT_CENEO');
		$product_description = Configuration::get('ITG_PRODUCT_DESCRIPTION');
		$product_description_count = Configuration::get('ITG_PRODUCT_DESCRIPTION_COUNT');
		$stock = Configuration::get('PS_STOCK_MANAGEMENT');
		
		$title_position = Configuration::get('ITG_TITLE_POSITION_CENEO');
		$title = Configuration::get('ITG_TITLE_CENEO');
		
		$products = array();
		$product_list = array();
		$category = array();
		$ceneo_attrs_limit = 20;
		$product_images = Configuration::get('ITG_PRODUCT_IMAGE');		
		ini_set('MAX_EXECUTION_TIME', -1);
		set_time_limit(0);
		ini_set('memory_limit','-1');
		
		$default_image_name = 'home_default';
		$ImageType = ImageType::getImagesTypes();
		foreach ($ImageType as $it) {
			if ($it['id_image_type'] == $product_image) {
				$default_image_name = $it['name'];
			}
		}		
		$categorys = Db::getInstance()->executeS('SELECT * FROM '._DB_PREFIX_.'itg_categories_ceneo_mapping');
		if( !empty($categorys) )
		{
			if( $product_config == 1 ) // all products
			{
				foreach ($categorys as $key => $c) {
					$category[] = $c['id_shop'];
					$product_list2 = Product::getProducts($this->context->cookie->id_lang, 0, 5000, 'name', 'ASC', $c['id_shop'], true);
					$products[] = $this->removeDuplicate($product_list2);
				}				
				if (!empty($products)) {				
					$i = 0;
					foreach ($products as $product) {
						foreach ($product as $p) {
							$product_list[$i] = new Product($p['id_product'],false,$this->context->cookie->id_lang);
							$i++;
						}
					}						
				}
			} else {
				foreach ($categorys as $key => $c) {
					$category[] = $c['id_shop'];
				}				
				$products_list = Db::getInstance()->executeS('SELECT * FROM '._DB_PREFIX_.'itg_products_ceneo ORDER BY id_product ASC');
				$product_list = array();
				if (!empty($products_list)) {
					$i = 0;
					foreach ($products_list as $p) {
						$pCat = Product::getProductCategories($p['id_product']);
						$product_list[] = new Product($p['id_product'],false,$this->context->cookie->id_lang);
					}
				}
			}			
			if( !empty($product_list) )
			{				
				$start = microtime(true);				
				$product_list = $this->productZero($product_list);
				$link = new Link();				
				$dir_skapiec = dirname(__FILE__).'/../../';
				$dirs = $_SERVER["DOCUMENT_ROOT"].__PS_BASE_URI__;
				$file_dirs = $dir_skapiec."itg-ceneo.xml";				
				if (file_exists($file_dirs)) {
					unlink($file_dirs);
				}				
				$xmlWriter = new XMLWriter();
				$xmlWriter->openMemory();
				$xmlWriter->startDocument('1.0', 'UTF-8');				
				$xmlWriter->startElement('offers');
				$xmlWriter->writeAttribute('xmlns:xsi', "http://www.w3.org/2001/XMLSchema-instance");
				$xmlWriter->writeAttribute('version', "1");				
				foreach ($product_list as $key => $p) {
					$is_in = Db::getInstance()->getRow('SELECT * FROM '._DB_PREFIX_.'itg_products_ceneo WHERE id_product = '.$p->id);
					$xmlWriter->startElement('o');
					$xmlWriter->writeAttribute('id', $p->id);					
					$productLink = $link->getProductLink($p->id, $p->link_rewrite, '', null, $this->context->cookie->id_lang);
					$xmlWriter->writeAttribute('url', $productLink);
					$xmlWriter->writeAttribute('price', $this->getProductPrice($p->id));					
					/* start available */
					if ($product_delay_all == 0) {
						if ($product_config == 1) {
							if ($p->quantity > 0) {
								if( !empty($is_in) )
									$xmlWriter->writeAttribute('avail', $is_in['delay']);
								else
									$xmlWriter->writeAttribute('avail', '1');
							} else {
								if (!empty($is_in)) {
									$xmlWriter->writeAttribute('avail', $is_in['delay']);
								} else {
									$xmlWriter->writeAttribute('avail', $product_delay);
								}
							}
						} else {
							if (!empty($is_in)) {
								$xmlWriter->writeAttribute('avail', $is_in['delay']);
							} else {
								$xmlWriter->writeAttribute('avail', '1');
							}
						}
					} else {
						$xmlWriter->writeAttribute('avail', $product_delay_all);
					}
					/* end available */					
					/* start stock */
					if ($stock == 1) {
						if ($p->quantity < 1) {
							$pq = 0;
						} else {
							$pq = $p->quantity;			
						}							
						$xmlWriter->writeAttribute('stock', $pq);
					}
					/* end stock */						
					/* start basket */
					if (!empty($is_in)) {
						if ($is_in['basket'] == 1) {
							$xmlWriter->writeAttribute('basket', '1');
						}
					}
					/* end basket */					
					$xmlWriter->writeAttribute('set', '0');					
					/* start weight */
					if ($p->weight > 0) {
						$xmlWriter->writeAttribute('weight', number_format($p->weight,2));
					}
					/* end weight */					
					/* start category */
					$new_cat = $this->getDirCeneoDefault(Configuration::get('ITG_CATEGORY_DEFAULT'));
					$default_cat = $p->id_category_default;
					$ctg[0] = Db::getInstance()->getRow('SELECT * FROM '._DB_PREFIX_.'itg_categories_ceneo_mapping WHERE id_shop = '.$default_cat);
					$ca = $this->goCategory($ctg);
					if( !empty($ca[0]['ceneo_name']) )
						$new_cat = '/'.$ca[0]['ceneo_name'];
					$xmlWriter->writeElement('cat', $new_cat);
					/* end category */					
					/* start name */
					if (!empty($is_in['name'])) {
					    if ($title_position == 2) {
						$new_name = $p->name.' '.$is_in['name'].(!empty($title)?' '.$title:'');
					    } else {
					        $new_name = (!empty($title)?$title.' ':'').$p->name.' '.$is_in['name'];
					    }
					} else {
					    if ($title_position == 2) {
						$new_name = $p->name.(!empty($title)?' '.$title:'');
					    } else {
					        $new_name = (!empty($title)?$title.' ':'').$p->name;
					    }
					}
					$xmlWriter->writeElement('name', $new_name);
					/* end name */					
					/* start desc */
					$desc = '';
					if ($product_description == 1) {
						$desc = strip_tags(trim($p->description_short));
					} else if($product_description == 2) {
						$desc = mb_substr(strip_tags(trim($p->description)),0,$product_description_count);
					} else {
						if (!empty($is_in['description'])) {
							$desc = strip_tags(trim($is_in['description']));
						} else {
							$desc = mb_substr(strip_tags(trim($p->description)),0,$product_description_count);
						}
					}
					$xmlWriter->writeElement('desc', $desc);
					/* end desc */					
					/* start images */
					if ($product_images == 1) {
						$img = Image::getCover($p->id);
						if ($img['id_image']) {
							$fileName = $link->getImageLink($p->link_rewrite,$img['id_image'],$default_image_name);
							$iUrl = Tools::getShopProtocol().$fileName;							
							$xmlWriter->startElement('imgs');
							$xmlWriter->startElement('main');
							$xmlWriter->writeAttribute('url', $iUrl);
							$xmlWriter->endElement();
							$xmlWriter->endElement();
						}
					} else {
						$images = $p->getImages($this->context->cookie->id_lang);
						if (!empty($images)) {
							$xmlWriter->startElement('imgs');								
							foreach ($images as $key => $i) {
								$fileName = $link->getImageLink($p->link_rewrite, $i['id_image'], $default_image_name);
								$iUrl = Tools::getShopProtocol().$fileName;					
								if ($i['cover'] == 1) {
									$xmlWriter->startElement('main');
									$xmlWriter->writeAttribute('url', $iUrl);
									$xmlWriter->endElement();
								} else {
									$xmlWriter->startElement('i');
									$xmlWriter->writeAttribute('url', $iUrl);
									$xmlWriter->endElement();
								}
							}							
							$xmlWriter->endElement();
						}
					}
					/* end images */					
					/* start attributes */
					$xmlWriter->startElement('attrs');					
					$nbAttrs = 0;
					if ($p->manufacturer_name != '') {
						$xmlWriter->startElement('a');
						$xmlWriter->writeAttribute('name', 'Producent');
						$xmlWriter->text($p->manufacturer_name);
						$xmlWriter->endElement();
						$nbAttrs++;
					}
					if ($p->reference != '') {
						$xmlWriter->startElement('a');
						$xmlWriter->writeAttribute('name', 'Kod_producenta');
						$xmlWriter->text($p->reference);
						$xmlWriter->endElement();
						$nbAttrs++;
					}
					if($p->ean13 != ''){
						$xmlWriter->startElement('a');
						$xmlWriter->writeAttribute('name', 'EAN');
						$xmlWriter->text($p->ean13);
						$xmlWriter->endElement();
						$nbAttrs++;
					}
					$feature = Product::getFrontFeaturesStatic($this->context->cookie->id_lang, $p->id);
					foreach($feature as $f) {
						$xmlWriter->startElement('a');
						$xmlWriter->writeAttribute('name', $f['name']);
						$xmlWriter->text($f['value']);
						$xmlWriter->endElement();
						
						$nbAttrs++;
						if($ceneo_attrs_limit && $nbAttrs==10)
							break;
					}
					$xmlWriter->endElement();
					/* end attributes */					
					
					$xmlWriter->endElement();
					
					if (0 == ($key % 1000)) {
						file_put_contents($file_dirs, $xmlWriter->flush(true), FILE_APPEND);
					}
				}
				
				$xmlWriter->endElement();
				
				file_put_contents($file_dirs, $xmlWriter->flush(true), FILE_APPEND);
				
				$stop = microtime(true);
				
				header('Location: '.AdminController::$currentIndex.'&configure='.$this->name.'&token='.Tools::getAdminTokenLite('AdminModules'));
				
//				$seconds = $stop - $start;
// 				echo "<br />Start: " . $start . PHP_EOL;
// 				echo "<br />Stop: " . $stop . PHP_EOL;
//				echo "<br />Seconds: " . number_format($seconds,3) . PHP_EOL;
//				echo "<br />Memory peak: " . number_format(memory_get_peak_usage() / 1048576 ,3). 'MB' . PHP_EOL;
//				exit();
			}
		}		
	}
	
	public function generujSkapiec()
	{		
		$product_config = Configuration::get('ITG_PRODUCT_SKAPIEC');
		$product_stan = Configuration::get('ITG_PRODUCT_0');
		$product_image = Configuration::get('ITG_IMAGE_DEFAULT');
		$product_price = Configuration::get('ITG_PRICE');
		$product_description = Configuration::get('ITG_PRODUCT_DESCRIPTION3');
		$product_description_count = Configuration::get('ITG_PRODUCT_DESCRIPTION_COUNT');
		$stock = Configuration::get('PS_STOCK_MANAGEMENT');
		$products = array();
		$product_list = array();
		$category = array();
		$product_images = Configuration::get('ITG_PRODUCT_IMAGE');
		
		ini_set('memory_limit','512M');
	
		$default_image_name = 'home_default';
		$ImageType = ImageType::getImagesTypes();
		foreach($ImageType as $it)
		{
			if( $it['id_image_type'] == $default_image)
				$default_image_name = $it['name'];
		}
	
		$categorys = Db::getInstance()->executeS('SELECT * FROM '._DB_PREFIX_.'itg_categories_ceneo_mapping');
		if( !empty($categorys) )
		{
			foreach($categorys as $key => $c)
			{
				$category[$key] = $c['id_shop'];
				$products[$key] = Product::getProducts($this->context->cookie->id_lang, 0, 5000, 'name', 'ASC', $c['id_shop'], true);
			}
				
			if( !empty($products) )
			{
				$i = 0;
				foreach($products as $product)
					foreach($product as $p)
					{
						if($product_config == 1)
						{
							$product_list[$i] = new Product($p['id_product'],true,$this->context->cookie->id_lang,1);
							$i++;
						}
						else
						{
							$is_in = Db::getInstance()->getRow('SELECT * FROM '._DB_PREFIX_.'itg_products_skapiec WHERE id_product = '.$p['id_product']);
							if( !empty($is_in) )
							{
								$product_list[$i] = new Product($p['id_product'],false,$this->context->cookie->id_lang);
								$i++;
							}
						}
					}
			}
				
			if( !empty($product_list) )
			{
				ob_start();
// 				$xmlWriter = new XMLWriter();
// 				$xmlWriter->openMemory();
// 				$xmlWriter->startDocument('1.0', 'UTF-8');
// 				$xmlWriter->startElement('xmldata');
// 				$xmlWriter->writeAttribute('xmlns:xsi', "http://www.w3.org/2001/XMLSchema-instance");
// 				$xmlWriter->writeAttribute('version', "12.0");
				
				$xml = new DOMDocument();
				$xml->loadXML('<?xml version="1.0" encoding="UTF-8"?>
						   		<xmldata>
								  <version>12.0</version>
								  <header/>
								  <category/>
								  <data/>
								</xmldata>');
				$header = $xml->getElementsByTagName('header')->item(0);
				$shopName = $xml->createElement('name', Configuration::get('PS_SHOP_NAME'));
				$header->appendChild($shopName);
				$shopWWW = $xml->createElement('www', $this->context->shop->getBaseURL());
				$header->appendChild($shopWWW);
				$shopTime = $xml->createElement('time', date('Y-m-d'));
				$header->appendChild($shopTime);
				$offers = $xml->getElementsByTagName('data')->item(0);
				$product_list = $this->productZero($product_list);
				$link = new Link();

				$product_list = $this->removeDuplicate($product_list);
	
				foreach($product_list as $p)
				{
					$prod = new Product($p->id,true,$this->context->cookie->id_lang);
					$is_in = Db::getInstance()->getRow('SELECT * FROM '._DB_PREFIX_.'itg_products_skapiec WHERE id_product = '.$prod->id);
					
					$offer = $xml->createElement('item');
					$offers->appendChild($offer);
					$pId = $xml->createElement('compid', $p->id);
					$offer->appendChild($pId);
					
					$producent = $xml->createElement('vendor', $prod->manufacturer_name);
					$offer->appendChild($producent);
					
					if( !empty($is_in['name']) )
						$new_name = $prod->name.' '.$is_in['name'];
					else
						$new_name = $prod->name;
											
					$pName = $xml->createElement('name');
					$pName->appendChild($xml->createCDATASection($new_name));
					$offer->appendChild($pName);
					
					$pPrice = $xml->createElement('price', $this->getProductPrice($p->id));
					$offer->appendChild($pPrice);
					$cId = $xml->createElement('catid', $prod->id_category_default);
					$offer->appendChild($cId);
					
					if( $product_images == 1)
					{
						$img = Image::getCover($p->id);
						if($img['id_image'])
						{
							$fileName = $link->getImageLink($prod->link_rewrite,$img['id_image'],$default_image_name);
							$iUrl = Tools::getShopProtocol().$fileName;
							$image = $xml->createElement('foto', $iUrl);
							$offer->appendChild($image);
						}
					}
					else {
						$images = $prod->getImages($this->context->cookie->id_lang);
						if( !empty($images) )
						{								
							$gallery = $xml->createElement('gallery');
							foreach($images as $key => $i)
							{
								$fileName = $link->getImageLink($prod->link_rewrite,$i['id_image'],$default_image_name);
								$iUrl = Tools::getShopProtocol().$fileName;
								
								if( $i['cover'] == 1 ){
									$image = $xml->createElement('foto', $iUrl);
									$offer->appendChild($image);
								}else{
									$image = $xml->createElement('foto', $iUrl);
									$gallery->appendChild($image);
								}
							}
							$offer->appendChild($gallery);
						}
					}
					
					$descs = '';
					if($product_description == 1)
						$descs = strip_tags(trim($prod->description_short));
					else if($product_description == 2){
						$descs = mb_substr(strip_tags(trim($prod->description)),0,$product_description_count);
					}
					else{
						if( !empty($is_in['description']) ){
							$descs = strip_tags(trim($is_in['description']));
						} else {
							$descs = mb_substr(strip_tags(trim($prod->description)),0,$product_description_count);
						}
					}
					
					$descCDATA = $xml->createCDATASection($descs);
					$desc = $xml->createElement('desclong');
					$desc->appendChild($descCDATA);
					$offer->appendChild($desc);
					$url = $xml->createElement('url',$link->getProductLink($p->id,$prod->link_rewrite,'',null,$this->context->cookie->id_lang));
					$offer->appendChild($url);
					
				}
// 				exit();
				$dir_skapiec = dirname(__FILE__).'/../../';
				$dirs = $_SERVER["DOCUMENT_ROOT"].__PS_BASE_URI__;
				chmod($dirs,0777);
				if( $xml->save($dir_skapiec."itg-skapiec.xml") )
				{
					chmod($dirs."itg-skapiec.xml",0777);
					$return = 'ok';
					ini_set('memory_limit','256M');
					header('Location: '.AdminController::$currentIndex.'&configure='.$this->name.'&token='.Tools::getAdminTokenLite('AdminModules'));
				}
			}
		}
	}
	
	public function generujNokaut()
	{
		global $cookie;
		$product_config = Configuration::get('ITG_PRODUCT_NOKAUT');
		$product_stan = Configuration::get('ITG_PRODUCT_0');
		$product_image = Configuration::get('ITG_IMAGE_DEFAULT');
		$product_price = Configuration::get('ITG_PRICE');
		$product_delay = Configuration::get('ITG_PRODUCTQUANTITY_0_NOKAUT');
		$product_description = Configuration::get('ITG_PRODUCT_DESCRIPTION2');
		$product_description_count = Configuration::get('ITG_PRODUCT_DESCRIPTION_COUNT');
		$free_shipping = Configuration::get('ITG_FREESHIPPING_NOKAUT');		
		$stock = Configuration::get('PS_STOCK_MANAGEMENT');
		$products = array();
		$product_list = array();
		$category = array();
		$product_images = Configuration::get('ITG_PRODUCT_IMAGE');
		
		ini_set('memory_limit','512M');
	
		$default_image_name = 'home_default';
		$ImageType = ImageType::getImagesTypes();
		foreach($ImageType as $it)
		{
			if( $it['id_image_type'] == $default_image)
				$default_image_name = $it['name'];
		}
	
		$categorys = Db::getInstance()->executeS('SELECT * FROM '._DB_PREFIX_.'itg_categories_ceneo_mapping');
		if( !empty($categorys) )
		{
			foreach($categorys as $key => $c)
			{
				$category[$key] = $c['id_shop'];
				$products[$key] = Product::getProducts($this->context->cookie->id_lang, 0, 5000, 'name', 'ASC', $c['id_shop'], true);
			}
	
			if( !empty($products) )
			{
				$i = 0;
				foreach($products as $product)
					foreach($product as $p)
					{
						if($product_config == 1)
						{
							$product_list[$i] = new Product($p['id_product'],true,$this->context->cookie->id_lang);
							$i++;
						}
						else
						{
							$is_in = Db::getInstance()->getRow('SELECT * FROM '._DB_PREFIX_.'itg_products_nokaut WHERE id_product = '.$p['id_product']);
							if( !empty($is_in) )
							{
								$product_list[$i] = new Product($p['id_product'],false,$this->context->cookie->id_lang);
								$i++;
							}
						}
					}
			}
	
			if( !empty($product_list) )
			{
				ob_start();
				$xml = new DOMDocument();
				$xml->loadXML('<?xml version="1.0" encoding="UTF-8"?>
						   <!DOCTYPE nokaut SYSTEM "http://www.nokaut.pl/integracja/nokaut.dtd">
							<nokaut>
								<offers/>
							</nokaut>');
				$offers = $xml->getElementsByTagName('offers')->item(0);				
				$product_list = $this->productZero($product_list);
				$link = new Link();
				
				$product_list = $this->removeDuplicate($product_list);
	
				foreach($product_list as $p)
				{
					$prod = new Product($p->id,true,$this->context->cookie->id_lang,1);
					$is_in = Db::getInstance()->getRow('SELECT * FROM '._DB_PREFIX_.'itg_products_nokaut WHERE id_product = '.$p->id);
					
					$offer = $xml->createElement('offer');
					$offers->appendChild($offer);
					
					if( !empty($is_in['name']) )
						$new_name = $prod->name.' '.$is_in['name'];
					else
						$new_name = $prod->name;
					
					$pName = $xml->createElement('name');
					$pName->appendChild($xml->createCDATASection($new_name));
					$offer->appendChild($pName);
					
					$pId = $xml->createElement('id', $p->id);
					$offer->appendChild($pId);
					
					$descs = '';
					if($product_description == 1)
						$descs = strip_tags(trim($prod->description_short));
					else if($product_description == 2){
						$descs = mb_substr(strip_tags(trim($prod->description)),0,$product_description_count);
					}
					else{
						if( !empty($is_in['description']) ){
							$descs = strip_tags(trim($is_in['description']));
						} else {
							$descs = mb_substr(strip_tags(trim($prod->description)),0,$product_description_count);
						}
					}
					
					$descCDATA = $xml->createCDATASection($descs);
					$desc = $xml->createElement('description');
					$desc->appendChild($descCDATA);
					$offer->appendChild($desc);
					
					$productLink = $link->getProductLink($p->id);
					$url = $xml->createElement('url', $productLink);
					$offer->appendChild($url);
					
					$pPrice = $xml->createElement('price', $this->getProductPrice($p->id));
					$offer->appendChild($pPrice);
						
					$prc = $this->getProductPrice($p->id);
					if( $prc >= $free_shipping && $free_shipping > 0 )
					{
						$fs = $xml->createElement('shipping');
						$fs->appendChild($xml->createCDATASection(0));
						$offer->appendChild($fs);
					}
						
					$cId = $xml->createElement('category');
					$cId->appendChild($xml->createCDATASection($prod->id_category_default));
					$offer->appendChild($cId);
					
					$producent = $xml->createElement('producer', $prod->manufacturer_name);
					$offer->appendChild($producent);
					
					if( $product_images == 1)
					{
						$img = Image::getCover($p->id);
						if($img['id_image'])
						{
							$fileName = $link->getImageLink($prod->link_rewrite,$img['id_image'],$default_image_name);
							$iUrl = Tools::getShopProtocol().$fileName;
							$image = $xml->createElement('image', $iUrl);
							$offer->appendChild($image);
						}
					}
					else {
						$images = $prod->getImages($this->context->cookie->id_lang);
						if( !empty($images) )
						{					
							$gallery = $xml->createElement('gallery');
							foreach($images as $key => $i)
							{
								$fileName = $link->getImageLink($prod->link_rewrite,$i['id_image'],$default_image_name);
								$iUrl = Tools::getShopProtocol().$fileName;
								
								if( $i['cover'] == 1 ){
									$image = $xml->createElement('image', $iUrl);
									$offer->appendChild($image);
								}else{
									$image = $xml->createElement('image', $iUrl);
									$gallery->appendChild($image);
								}
							}
							$offer->appendChild($gallery);
						}
					}
					
					if($product_config == 1)
					{
						if($prod->quantity > 0)
							$availability = $xml->createElement('availability', '0');
						else
							$availability = $xml->createElement('availability', $product_delay);
					}
					else
					{
						if( !empty($is_in) )
							$availability = $xml->createElement('availability', $is_in['delay']);
						else
							$availability = $xml->createElement('availability', '0');
					}
					$offer->appendChild($availability);
					
					if( !empty($prod->ean13))
					{
						$prop = $xml->createElement('property',$prod->ean13);
						$prop->setAttribute('name', 'ean13');
						$offer->appendChild($prop);
					}
					if( !empty($prod->reference))
					{
						$prop = $xml->createElement('property',$prod->reference);
						$prop->setAttribute('name', 'mpn');
						$offer->appendChild($prop);
					}
						
				}
				$dir_nokaut = dirname(__FILE__).'/../../';
				$dirs = $_SERVER["DOCUMENT_ROOT"].__PS_BASE_URI__;
				chmod($dirs,0777);
				if( $xml->save($dir_nokaut."itg-nokaut.xml") )
				{
					chmod($dirs."itg-nokaut.xml",0777);
					$return = 'ok';
					ini_set('memory_limit','256M');
					header('Location: '.AdminController::$currentIndex.'&configure='.$this->name.'&token='.Tools::getAdminTokenLite('AdminModules'));
				}
				else
					echo "Nie można zapisać pliku!";
			}
			else
				echo "Brak produktów!";
		}
	}
	
	private function getProductPrice($id_product, $id_product_attribute = NULL){
		return Product::getPriceStatic($id_product, true, $id_product_attribute, 2);
	}
	
	private function productZero($products)
	{
		$stock = Configuration::get('PS_STOCK_MANAGEMENT');
		$tab = array();
		if( $stock == 1 )
		{
			foreach($products as $p)
			{
				$pp = Product::getQuantity($p->id);
				//if( $p->quantity > 0 )
				if($pp > 0)
					$tab[] = $p;
			}
		}
		else 
			$tab = $products;
		
		return $tab;
	}
	
	private function removeDuplicate($products)
	{
		$tab = array();
		$produkty = array();
		foreach($products as $p)
		{
			if( !is_array($p) )
			{
				if( !in_array($p->id,$tab))
				{
					$tab[] = $p->id;
					$produkty[] = $p;
				}
			}
			else
			{
				if( !in_array($p['id_product'],$tab))
				{
					$tab[] = $p['id_product'];
					$produkty[] = $p;
				}
			}
		}
		return $produkty;
	}
	
}
?>