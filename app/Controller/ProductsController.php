<?php

/* Janakiraman */

App::uses('AppController', 'Controller');
App::uses('Spreadsheet_Excel_Reader', 'Vendor');

class ProductsController extends AppController {
    
	var $helpers = array('Html', 'Session', 'Javascript', 'Ajax', 'Common');
    
	public $uses = array('Product','Category','Brand','Store','ProductDetail', 
                      'ProductImage','User','Customer','Store');

  public $components = array('Img', 'Updown');
	/**
	 * ProductsController::admin_index()
	 * 
	 * @return void
	 */
	public function admin_index($storeId = null) {

    if ($storeId != '') {
      $products_detail = $this->Product->find('all',array(
                                  'conditions' => array('Product.store_id' => $storeId,
                                          'NOT'=> array('Product.status'=>3)),
                                  'order'=> array('Product.id DESC')));
    } else {
		  $products_detail = $this->Product->find('all',array(
                                            'conditions'=>array('NOT'=>array('Product.status'=>3)),
                                            'order'=>array('Product.id DESC')));
    }

    $stores = $this->Store->find('list', array(
                                'conditions'  =>  array('Store.status'=>1),
                                'fields'      =>  array('Store.id', 'Store.store_name')));

		$this->set(compact('products_detail', 'stores'));
	}
  	/**
	 * ProductsController::admin_add()
	 * 
	 * @return void
	 */
  //super admin add process
	public function admin_add() {

		if (!empty($this->request->data['Product']['product_name'])) {

       		$store_id = $this->request->data['Product']['store_id'];
            
            $Product_check = $this->Product->find('all', array(
            						'conditions'=>array('Product.product_name'=>trim($this->request->data['Product']['product_name']),
                                                         'Product.store_id' => $store_id)));
            if (!empty($Product_check)) {

                    $this->Session->setFlash('<p>'.__('Product already exists', true).'</p>', 'default', 
                                                              array('class' => 'alert alert-danger'));
            } else {

                $this->request->data['Product']['store_id']        = $store_id;
                $this->request->data['Product']['brand_id'] =  ($this->request->data['Product']['brand_id'] != '') ? 
                                                                $this->request->data['Product']['brand_id'] : 0;

                $this->request->data['Product']['sub_category_id'] =  ($this->request->data['Product']['sub_category_id'] != '') ? 
                                                                $this->request->data['Product']['sub_category_id'] : 0;

                $this->Product->save($this->request->data['Product'], null, null);

                if($this->request->data['Product']['price_option'] == "single") {

                    $this->request->data['ProductDetail']['product_id']    = $this->Product->id;
                    $this->request->data['ProductDetail']['sub_name']      = (!empty($this->request->data['ProductDetail']['sub_name'])) ?
                                                                              $this->request->data['ProductDetail']['sub_name'] :
                                                                              $this->request->data['Product']['product_name'] ;
                  
                    $this->ProductDetail->save($this->request->data['ProductDetail'],null,null);

                } else {

                  $productDetails = $this->request->data['ProductDetail'];

                    foreach ($productDetails as $key => $value) {

                      if (is_array($value)) {

                          $value['product_id']    = $this->Product->id;
                          $this->ProductDetail->save($value, null, null);
                          $this->ProductDetail->id = '';
                      }
                    }
                }

                #Product image Upload
                if(!file_exists(WWW_ROOT.DS.'stores'.DS.$store_id)) {

                    $this->Img->mkdir(WWW_ROOT.DS.'stores'.DS.$store_id);
                    $path = WWW_ROOT.DS.'stores'.DS.$store_id;
                    $this->Img->mkdir($path.DS."products");
                    $path=$path.DS."products";
                    $this->Img->mkdir($path.DS."home");
                    $this->Img->mkdir($path.DS."carts");
                    $this->Img->mkdir($path.DS."product_details");
                    $this->Img->mkdir($path.DS."scrollimg");
                    $this->Img->mkdir($path.DS."original");
                }

                $root      = WWW_ROOT.DS.'stores'.DS.$store_id.DS."products".DS;
                $origpath  = $root."original".DS;
                $homepath  = $root."home".DS;
                $cartpath  = $root."carts".DS;
                $scrollimg = $root."scrollimg".DS;
                $prod_det_path = $root."product_details".DS;
                
                $allowed_ext = array('image/jpg', 'image/jpeg', 'image/png', 'image/gif');

                $productimages = $this->request->data['ProductImage'];
                foreach($productimages as $key => $value) {

                  $imagesizedata = getimagesize($value['tmp_name']);
                  if ($imagesizedata) {

                    if($value['name'] != "" && in_array($value['type'], $allowed_ext)) {

                        $newName    = str_replace(" ","-", uniqid()  . '.' .$value['name']); 
                        $targetdir = $origpath.DS;
                        
                        #Upload
                        $upload = $this->Img->upload($value['tmp_name'], $targetdir, $newName);
                        
                        #Resize
                        $this->Img->resampleGD($targetdir.DS.$newName, $homepath, $newName, 265, 265, 1, 0);
                        $this->Img->resampleGD($targetdir.DS.$newName, $cartpath, $newName, 78, 64, 1, 0);
                        $this->Img->resampleGD($targetdir.DS.$newName, $scrollimg, $newName, 67, 55, 1, 0);
                        $this->Img->resampleGD($targetdir.DS.$newName, $prod_det_path, $newName, 1024, 768, 1, 0);
                        
                        $product_images['product_id']  = $this->Product->id;
                        $product_images['store_id']    = $store_id;
                        $product_images['image']       = $value['name'];
                        $product_images['image_alias'] = $newName;

                        $this->ProductImage->save($product_images);
                        $this->ProductImage->id = "";

                  	}
                  }
                }


                $this->Session->setFlash('<p>'.__('Your Product has been saved', true).'</p>', 'default', 
                                                  array('class' => 'alert alert-success'));
                $this->redirect(array('controller' => 'Products','action' => 'index'));
            }
            
       }

		$brand_list     = $this->Brand->find('list',array(
                                                    'conditions'=>array('Brand.status'=>1),
                                                    'fields'=>array('Brand.id','Brand.brand_name')));
		$category_list  = $this->Category->find('list',
                                        array('conditions' => array('Category.parent_id'=>0,'Category.status'=>1),
                                              'fields'     => array('Category.id','Category.category_name')));

		$stores = $this->Store->find('list', array(
                                'conditions'=>array('Store.status'=>1),
                              'fields' => array('Store.id', 'Store.store_name')));
		$this->set(compact('brand_list','category_list', 'stores'));   
	}

    /**
     * ProductsController::admin_edit()
     * 
     * @param mixed $id
     * @return void
     */
    public function admin_edit($id = null) {

        if(!empty($this->request->data['Product']['product_name'])) {

        	$store_id = $this->request->data['Product']['store_id'];
          $product_check = $this->Product->find('first', array(
                                      'conditions'=>array(
                                      'Product.product_name'=>trim($this->request->data['Product']['product_name']),
                                      'Product.store_id' => $store_id,
                                      'NOT' => array('Product.id'=>$this->request->data['Product']['id']))));
          if(!empty($product_check)) {
                $this->Session->setFlash('<p>'.__('Product already exists', true).'</p>', 'default', 
                                                            array('class' => 'alert alert-danger'));
          } else {

              $this->request->data['Product']['brand_id'] =  ($this->request->data['Product']['brand_id'] != '') ? 
                                                                $this->request->data['Product']['brand_id'] : 0;
              $this->request->data['Product']['sub_category_id'] =  ($this->request->data['Product']['sub_category_id'] != '') ? 
                                                                $this->request->data['Product']['sub_category_id'] : 0;
              $this->Product->save($this->request->data['Product'], null, null);
              $this->ProductDetail->deleteAll(array('product_id' => $this->Product->id));

              if($this->request->data['Product']['price_option'] == "single") {

                  $this->request->data['ProductDetail']['product_id']    = $this->Product->id;
                  $this->request->data['ProductDetail']['sub_name']      = (!empty($this->request->data['ProductDetail']['sub_name'])) ?
                                                                              $this->request->data['ProductDetail']['sub_name'] :
                                                                              $this->request->data['Product']['product_name'] ;
                  $this->ProductDetail->save($this->request->data['ProductDetail'],null,null);

              } else {

                $productDetails = $this->request->data['ProductDetail'];
                  foreach ($productDetails as $key => $value) {
                    if (is_array($value)) {
                        $value['product_id']    = $this->Product->id;
                        $this->ProductDetail->save($value, null, null);
                        $this->ProductDetail->id = '';
                    }
                  }
              }

              $root      = WWW_ROOT.DS.'stores'.DS.$store_id.DS."products".DS;
              $origpath  = $root."original".DS;
              $homepath  = $root."home".DS;
              $cartpath  = $root."carts".DS;
              $scrollimg = $root."scrollimg".DS;
              $prod_det_path = $root."product_details".DS;
              
              $allowed_ext = array('image/jpg', 'image/jpeg', 'image/png', 'image/gif');

              $productimages = $this->request->data['ProductImage'];
              foreach($productimages as $key => $value) {

                $imagesizedata = getimagesize($value['tmp_name']);
                if ($imagesizedata) {

                  if($value['name'] != "" && in_array($value['type'], $allowed_ext)) {

                      $newName    = str_replace(" ","-", uniqid()  . '.' .$value['name']); 
                      $targetdir = $origpath.DS;
                      
                      #Upload
                      $upload = $this->Img->upload($value['tmp_name'], $targetdir, $newName);
                      
                      #Resize
                      $this->Img->resampleGD($targetdir.DS.$newName, $homepath, $newName, 265, 265, 1, 0);
                      $this->Img->resampleGD($targetdir.DS.$newName, $cartpath, $newName, 78, 64, 1, 0);
                      $this->Img->resampleGD($targetdir.DS.$newName, $scrollimg, $newName, 67, 55, 1, 0);
                      $this->Img->resampleGD($targetdir.DS.$newName, $prod_det_path, $newName, 1024, 768, 1, 0);
                      
                      $product_images['product_id']  = $this->Product->id;
                      $product_images['store_id']    = $store_id;
                      $product_images['image']       = $value['name'];
                      $product_images['image_alias'] = $newName;

                      $this->ProductImage->save($product_images);
                      $this->ProductImage->id = "";

                  }
                }
              }
              $this->Session->setFlash('<p>'.__('Your Product has been saved', true).'</p>', 'default', 
                                                              array('class' => 'alert alert-success'));
              $this->redirect(array('controller' => 'Products','action' => 'index'));
          }
        }
         
        $brand_list     = $this->Brand->find('list',array('fields'=>array('Brand.id','Brand.brand_name')));
        $category_list  = $this->Category->find('list',
                                              array('conditions'=>array('Category.parent_id'=>0,'Category.status'=>1),
                                                    'fields'=>array('Category.id','Category.category_name')));
        $stores = $this->Store->find('list', array(
                              'conditions'=>array('Store.status'=>1),
                              'fields' => array('Store.id', 'Store.store_name')));

        $getProductData = $this->Product->findById($id);
        $subcatList = $this->Category->find('list', array(
                                  'conditions' => array('Category.parent_id' => $getProductData['Product']['category_id'],
                                      'Category.status'=>1),
                                  'fields' => array('Category.id', 'Category.category_name')));

        $this->request->data = $getProductData;
        $this->set(compact('getProductData', 'subcatList', 'brand_list', 'category_list', 'stores'));
    }
    /**
     * ProductsController::admin_subCatList()
     * 
     * @return void
     */
    public function subCatList(){
        $id 	        = $this->request->data['id'];
		    $category_list  = $this->Category->find('list',
                                              array('conditions'=>array('Category.parent_id'=>$id,'Category.status'=>1),
                                                    'fields'=>array('Category.id',
                                                                    'Category.category_name')));
        $this->set(compact('category_list')); 
    }

    public function deleteProductImage() {
        $this->ProductImage->delete($this->request->data['id']);
        echo 'success';
        exit;
    }
    public function store_index($id = null) {
    $this->layout  = 'assets';
    $id = $this->Auth->User();
    $products_detail = $this->Product->find('all',array(
                                  'conditions'=>array(
                                  'Product.store_id'=>$id['Store']['id'],
                                  'NOT'=>array('Product.status'=>3)),
                                  'order'=>array('Product.id DESC')));
    $this->set(compact('products_detail'));
  }
    public function store_add() {
      $this->layout = 'assets';
      $stores_id    = $this->Auth->User();
      $store_id     = $stores_id['Store']['id'];
      if (!empty($this->request->data['Product']['product_name'])) {              
              $Product_check = $this->Product->find('all', array(
                          'conditions'=>array('Product.product_name'=>trim($this->request->data['Product']['product_name']),
                                                           'Product.store_id' => $store_id)));
              //echo "<pre>";print_r($Product_check);die();
              if (!empty($Product_check)) {

                      $this->Session->setFlash('<p>'.__('Product already exists', true).'</p>', 'default', 
                                                                array('class' => 'alert alert-danger'));
              } else {

                  $this->request->data['Product']['store_id']        = $store_id;
                  $this->request->data['Product']['brand_id'] =  ($this->request->data['Product']['brand_id'] != '') ? 
                                                                $this->request->data['Product']['brand_id'] : 0;
                  //$this->request->data['Product']['product_image'] = $this->request->data['product_image'][0];
                  $this->request->data['Product']['sub_category_id'] =  ($this->request->data['Product']['sub_category_id'] != '') ? 
                                                                $this->request->data['Product']['sub_category_id'] : 0;
                  $this->Product->save($this->request->data['Product'], null, null);

                  if($this->request->data['Product']['price_option'] == "single") {

                      $this->request->data['ProductDetail']['product_id']    = $this->Product->id;
                      $this->request->data['ProductDetail']['sub_name']      = (!empty($this->request->data['ProductDetail']['sub_name'])) ?
                                                                                $this->request->data['ProductDetail']['sub_name'] :
                                                                                $this->request->data['Product']['product_name'] ;
                    
                      $this->ProductDetail->save($this->request->data['ProductDetail'],null,null);

                  } else {

                    $productDetails = $this->request->data['ProductDetail'];

                      foreach ($productDetails as $key => $value) {

                        if (is_array($value)) {

                            $value['product_id']    = $this->Product->id;
                            $this->ProductDetail->save($value, null, null);
                            $this->ProductDetail->id = '';
                        }
                      }
                  }

                  #Product image Upload
                  if(!file_exists(WWW_ROOT.DS.'stores'.DS.$store_id)) {

                      $this->Img->mkdir(WWW_ROOT.DS.'stores'.DS.$store_id);
                      $path = WWW_ROOT.DS.'stores'.DS.$store_id;
                      $this->Img->mkdir($path.DS."products");
                      $path=$path.DS."products";
                      $this->Img->mkdir($path.DS."home");
                      $this->Img->mkdir($path.DS."carts");
                      $this->Img->mkdir($path.DS."product_details");
                      $this->Img->mkdir($path.DS."scrollimg");
                      $this->Img->mkdir($path.DS."original");
                  }

                  $root      = WWW_ROOT.DS.'stores'.DS.$store_id.DS."products".DS;
                  $origpath  = $root."original".DS;
                  $homepath  = $root."home".DS;
                  $cartpath  = $root."carts".DS;
                  $scrollimg = $root."scrollimg".DS;
                  $prod_det_path = $root."product_details".DS;
                  
                  $allowed_ext = array('image/jpg', 'image/jpeg', 'image/png', 'image/gif');

                  $productimages = $this->request->data['ProductImage'];
                  foreach($productimages as $key => $value) {

                    $imagesizedata = getimagesize($value['tmp_name']);
                    if ($imagesizedata) {

                      if($value['name'] != "" && in_array($value['type'], $allowed_ext)) {

                          $newName    = str_replace(" ","-", uniqid()  . '.' .$value['name']); 
                          $targetdir = $origpath.DS;
                          
                          #Upload
                          $upload = $this->Img->upload($value['tmp_name'], $targetdir, $newName);
                          
                          #Resize
                          $this->Img->resampleGD($targetdir.DS.$newName, $homepath, $newName, 265, 265, 1, 0);
                          $this->Img->resampleGD($targetdir.DS.$newName, $cartpath, $newName, 78, 64, 1, 0);
                          $this->Img->resampleGD($targetdir.DS.$newName, $scrollimg, $newName, 67, 55, 1, 0);
                          $this->Img->resampleGD($targetdir.DS.$newName, $prod_det_path, $newName, 1024, 768, 1, 0);
                          
                          $product_images['product_id']  = $this->Product->id;
                          $product_images['store_id']    = $store_id;
                          $product_images['image']       = $value['name'];
                          $product_images['image_alias'] = $newName;

                          $this->ProductImage->save($product_images);
                          $this->ProductImage->id = "";

                      }
                    }    
                  }


                  $this->Session->setFlash('<p>'.__('Your Product has been saved', true).'</p>', 'default', 
                                                    array('class' => 'alert alert-success'));
                  $this->redirect(array('controller' => 'Products','action' => 'index'));
              }
              
         }

      $brand_list     = $this->Brand->find('list', array(
                                          'conditions'=>array('Brand.status'=>1),
                                          'fields'=>array('Brand.id','Brand.brand_name')));
      $category_list  = $this->Category->find('list', array(
                                          'conditions' => array('Category.parent_id'=>0,'Category.status'=>1),
                                          'fields'     => array('Category.id','Category.category_name')));

      $stores = $this->Store->find('list', array(
                                'conditions'=>array('Store.status'=>1),
                                'fields' => array('Store.id', 'Store.store_name')));
      $this->set(compact('brand_list','category_list', 'stores', 'store_id'));   
    }   
     public function store_edit($id = null) {
        $this->layout = 'assets';
        $stores_id    = $this->Auth->User();
        $store_id     = $stores_id['Store']['id'];          
        if(!empty($this->request->data['Product']['product_name'])) {
          $product_check = $this->Product->find('first', array(
                                      'conditions'=>array(
                                      'Product.product_name'=>trim($this->request->data['Product']['product_name']),
                                      'Product.store_id' => $store_id,
                                      'NOT' => array('Product.id'=>$this->request->data['Product']['id']))));
          if(!empty($product_check)) {
                $this->Session->setFlash('<p>'.__('Product already exists', true).'</p>', 'default', 
                                                            array('class' => 'alert alert-danger'));
          } else {
              $this->request->data['Product']['brand_id'] =  ($this->request->data['Product']['brand_id'] != '') ? 
                                                                $this->request->data['Product']['brand_id'] : 0;
              $this->request->data['Product']['sub_category_id'] =  ($this->request->data['Product']['sub_category_id'] != '') ? 
                                                                $this->request->data['Product']['sub_category_id'] : 0;
              $this->Product->save($this->request->data['Product'], null, null);
              $this->ProductDetail->deleteAll(array('product_id' => $this->Product->id));

              if($this->request->data['Product']['price_option'] == "single") {

                  $this->request->data['ProductDetail']['product_id']    = $this->Product->id;
                  $this->request->data['ProductDetail']['sub_name']      = (!empty($this->request->data['ProductDetail']['sub_name'])) ?
                                                                              $this->request->data['ProductDetail']['sub_name'] :
                                                                              $this->request->data['Product']['product_name'] ;
                  $this->ProductDetail->save($this->request->data['ProductDetail'],null,null);

              } else {

                $productDetails = $this->request->data['ProductDetail'];
                  foreach ($productDetails as $key => $value) {
                    if (is_array($value)) {
                        $value['product_id']    = $this->Product->id;
                        $this->ProductDetail->save($value, null, null);
                        $this->ProductDetail->id = '';
                    }
                  }
              }

              $root      = WWW_ROOT.DS.'stores'.DS.$store_id.DS."products".DS;
              $origpath  = $root."original".DS;
              $homepath  = $root."home".DS;
              $cartpath  = $root."carts".DS;
              $scrollimg = $root."scrollimg".DS;
              $prod_det_path = $root."product_details".DS;
              
              $allowed_ext = array('image/jpg', 'image/jpeg', 'image/png', 'image/gif');

              $productimages = $this->request->data['ProductImage'];
              foreach($productimages as $key => $value) {

                $imagesizedata = getimagesize($value['tmp_name']);
                if ($imagesizedata) {

                  if($value['name'] != "" && in_array($value['type'], $allowed_ext)) {

                      $newName    = str_replace(" ","-", uniqid()  . '.' .$value['name']); 
                      $targetdir = $origpath.DS;
                      
                      #Upload
                      $upload = $this->Img->upload($value['tmp_name'], $targetdir, $newName);
                      
                      #Resize
                      $this->Img->resampleGD($targetdir.DS.$newName, $homepath, $newName, 265, 265, 1, 0);
                      $this->Img->resampleGD($targetdir.DS.$newName, $cartpath, $newName, 78, 64, 1, 0);
                      $this->Img->resampleGD($targetdir.DS.$newName, $scrollimg, $newName, 67, 55, 1, 0);
                      $this->Img->resampleGD($targetdir.DS.$newName, $prod_det_path, $newName, 1024, 768, 1, 0);
                      
                      $product_images['product_id']  = $this->Product->id;
                      $product_images['store_id']    = $store_id;
                      $product_images['image']       = $value['name'];
                      $product_images['image_alias'] = $newName;

                      $this->ProductImage->save($product_images);
                      $this->ProductImage->id = "";
                  }
                }    
              }

              $this->Session->setFlash('<p>'.__('Your Product has been saved', true).'</p>', 'default', 
                                                              array('class' => 'alert alert-success'));
              $this->redirect(array('controller' => 'Products','action' => 'index'));
          }
        }

         $brand_list     = $this->Brand->find('list',array(
                                                 'conditions'=>array('Brand.status'=>1),
                                                 'fields'=>array('Brand.id','Brand.brand_name')));
         $category_list  = $this->Category->find('list',
                                                 array('conditions' => array('Category.parent_id'=>0,'Category.status'=>1),
                                                     'fields'     => array('Category.id','Category.category_name')));
        $stores = $this->Store->find('list', array(
                                'conditions'=>array('Store.status'=>1),
                              'fields' => array('Store.id', 'Store.store_name')));

        $getProductData = $this->Product->findById($id);
        $subcatList = $this->Category->find('list', array(
                                  'conditions' => array('Category.parent_id' => $getProductData['Product']['category_id'],
                                      'Category.status'=>1),
                                  'fields' => array('Category.id', 'Category.category_name')));

        $this->request->data = $getProductData;
        $this->set(compact('getProductData', 'subcatList', 'brand_list', 'category_list', 'stores'));
    }

    public function importProduct() {

        $store_id = ($this->Auth->User('role_id') == 1) ? 
                                $this->request->data['Product']['store_id'] :
                                $this->Auth->User('Store.id');

        $allowed_ext = array('jpg', 'jpeg', 'png', 'gif');

        $count = $exists = 0;

        if (!empty($store_id)) {
          $file = $this->data['excel']['name'];
          move_uploaded_file($this->data['excel']['tmp_name'], WWW_ROOT .'Excel'.DS.$file);
          $data = new Spreadsheet_Excel_Reader(WWW_ROOT . 'Excel'.DS.$file, true);

          if (!empty($data)) {

            $result = $data->sheets['0']['cells'];
            array_splice($result,0,1);

            foreach($result as $key=>$value) {

              $product = $this->ProductDetail->find('first', array(
                            'conditions'=>array('Product.store_id' => $store_id,
                                        'OR' => array('Product.product_name' =>trim($value[1]),
                                                      'ProductDetail.product_code' => $value[9]))));
              if(empty($product)) {

                $product['id']                  = '';
                $product['store_id']            = $store_id;
                $product['product_name']        = $value[1];
                $product['category_id']         = $value[2];
                $product['sub_category_id']     = $value[3];
                $product['price_option']        = $value[4];
                $product['product_description'] = $value[10];
                $product['brand_id']            = 0;

                if ($this->Product->save($product, null, null)) {

                  $ProductDetail['id']            = '';
                  $ProductDetail['product_id']    = $this->Product->id;
                  $ProductDetail['sub_name']      = $value[5];
                  $ProductDetail['orginal_price'] = $value[6];
                  $ProductDetail['compare_price'] = $value[7];
                  $ProductDetail['quantity']      = $value[8];
                  $ProductDetail['product_code']  = $value[9];

                  $this->ProductDetail->save($ProductDetail,null,null);

                  // Product Image Name onls Save
                  $images = explode(',', $value[11]);

                  foreach ($images as $key => $val) {

                    $imgType = explode('.', $val);

                    if (in_array($imgType[1], $allowed_ext)) {

                        //$newName    = str_replace(" ","-", uniqid()  . '.' .$val);
                        $product_images['product_id']  = $this->Product->id;
                        $product_images['store_id']    = $store_id;
                        $product_images['image']       = $val;
                        $product_images['image_alias'] = $val;

                        $this->ProductImage->save($product_images);
                        $this->ProductImage->id = "";
                    }
                  }

                  $count += 1;
                }
              } else {
                  $exists +=1;
              }
            }

            if (!empty($count)) {
               // $this->Session->setFlash('<p>'.__('Successfully '.$count.' Product Imported and '.$exists.' Product already exists', true).'</p>', 'default', 
               //                                      array('class' => 'alert alert-success'));

              $this->Session->setFlash('<p>'.__('Successfully items Imported', true).'</p>', 'default', 
                                                    array('class' => 'alert alert-success'));
            } else {
              $this->Session->setFlash('<p>'.__('Items already exists', true).'</p>', 'default', 
                                                    array('class' => 'alert alert-danger'));
            }
          } else {

              $this->Session->setFlash('<p>'.__('The file is not readable. Please import Xls format file', true).'</p>', 'default', 
                                                    array('class' => 'alert alert-danger'));

          }
        } else {
          $this->Session->setFlash('<p>'.__('Store Name Missing', true).'</p>', 'default', 
                                                array('class' => 'alert alert-danger'));
        }

        if ($this->Auth->User('role_id') == 1) {
            $this->redirect(array('controller' => 'products','action' => 'index','admin' => true));
        } else {
            $this->redirect(array('controller' => 'products','action' => 'index','store' => true));
        }
    }

    public function batchCodeCheck() {

        $storeId   = $this->request->data['storeId'];
        $productId = $this->request->data['productId'];
        $batchCode = $this->ProductDetail->find('all', array(
                              'conditions' => array('Product.store_id' => $storeId,
                                      'NOT' => array('Product.id' => $productId)),
                              'fields' => array('ProductDetail.product_code')));

        foreach ($batchCode as $key => $value) {
            $batchCodes .= $value['ProductDetail']['product_code'].',';
        }
        echo rtrim($batchCodes, ",");
        //print_r($batchCodes);
        exit;

    }


    public function download($filename, $refName) {
        $path = WWW_ROOT."Excel".DS;
        $flagname = $this->Updown->downloadFile('groceryExcel.xls', 'groceryExcel.xls', $path);
        exit();
    }


    public function importImages() {

     
        $count = 0;
        $store_id = $this->Auth->User('Store.id');

        #Product image Upload
        if(!file_exists(WWW_ROOT.DS.'stores'.DS.$store_id)) {

            $this->Img->mkdir(WWW_ROOT.DS.'stores'.DS.$store_id);
            $path = WWW_ROOT.DS.'stores'.DS.$store_id;
            $this->Img->mkdir($path.DS."products");
            $path=$path.DS."products";
            $this->Img->mkdir($path.DS."home");
            $this->Img->mkdir($path.DS."carts");
            $this->Img->mkdir($path.DS."product_details");
            $this->Img->mkdir($path.DS."scrollimg");
            $this->Img->mkdir($path.DS."original");
        }

        $root      = WWW_ROOT.DS.'stores'.DS.$store_id.DS."products".DS;
        $origpath  = $root."original".DS;
        $homepath  = $root."home".DS;
        $cartpath  = $root."carts".DS;
        $scrollimg = $root."scrollimg".DS;
        $prod_det_path = $root."product_details".DS;
        
        $allowed_ext = array('image/jpg', 'image/jpeg', 'image/png', 'image/gif');


        $productimages = $this->request->data['ProductImage'];

        foreach($productimages as $key => $value) {

          $productImageDetail = $this->ProductImage->findByImageAndStoreId($value['name'], $store_id);

          $imagesizedata = getimagesize($value['tmp_name']);
          if ($imagesizedata) {

            if($value['name'] != "" && (!empty($productImageDetail)) && in_array($value['type'], $allowed_ext)) {

                $newName   = $productImageDetail['ProductImage']['image_alias'];
                $targetdir = $origpath.DS;
                
                #Upload
                $upload = $this->Img->upload($value['tmp_name'], $targetdir, $newName);
                
                #Resize
                $this->Img->resampleGD($targetdir.DS.$newName, $homepath, $newName, 265, 265, 1, 0);
                $this->Img->resampleGD($targetdir.DS.$newName, $cartpath, $newName, 78, 64, 1, 0);
                $this->Img->resampleGD($targetdir.DS.$newName, $scrollimg, $newName, 67, 55, 1, 0);
                $this->Img->resampleGD($targetdir.DS.$newName, $prod_det_path, $newName, 1024, 768, 1, 0);

                $count++;
            }
          }    
        }


        if (!empty($count)) {
          $this->Session->setFlash('<p>'.__('Successfully items images uploaded'+$count, true).'</p>', 'default', 
                                                array('class' => 'alert alert-success'));
        } else {
          $this->Session->setFlash('<p>'.__('Items images not uploaded', true).'</p>', 'default', 
                                                array('class' => 'alert alert-danger'));
        }

        
        if ($this->Auth->User('role_id') == 1) {
            $this->redirect(array('controller' => 'products','action' => 'index','admin' => true));
        } else {
            $this->redirect(array('controller' => 'products','action' => 'index','store' => true));
        }

    }
}