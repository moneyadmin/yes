<?php

namespace amilna\yes\controllers;

use Yii;
use amilna\yes\models\Order;
use amilna\yes\models\OrderSearch;
use amilna\yes\models\Customer;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\helpers\ArrayHelper;

/**
 * OrderController implements the CRUD actions for Order model.
 */
class OrderController extends Controller
{
    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['post'],
                ],
            ],
        ];
    }
    
     public function actions()
        {
            return [
                'captcha' => [					
                    'class' => 'yii\captcha\CaptchaAction',                    
                    'testLimit'=>1,
                ],
            ];
        }

    /**
     * Lists all Order models.
     * @params string $format, array $arraymap, string $term
     * @return mixed
     */
    public function actionIndex($format= false,$arraymap= false,$term = false)
    {
        $searchModel = new OrderSearch();        
        $req = Yii::$app->request->queryParams;
        if ($term) { $req[basename(str_replace("\\","/",get_class($searchModel)))]["term"] = $term;}        
        $dataProvider = $searchModel->search($req);				
		
		if (Yii::$app->request->post('hasEditable')) {			
			$Id = Yii::$app->request->post('editableKey');
			$model = Order::findOne($Id);
			$model->captchaRequired = false;
	 
			$out = json_encode(['id'=>$Id,'output'=>'', 'message'=>'','data'=>'null']);	 			
			$post = [];						
			
			$posted = current($_POST['OrderSearch']);			
			$post['Order'] = $posted;						
			
			$transaction = Yii::$app->db->beginTransaction();
			try {				
				if ($model->load($post)) {
								
					$model->complete_time = date('Y-m-d H:i:s');
					if ($model->save())
					{
						if ($model->status == 1)
						{
							$model->createSales();
						}
						elseif ($model->status == 0)
						{
							$model->deleteSales();
						}
					}
					else
					{
						$model->attributes = $model->oldAttributes;
					}	
						
					$output = '';	 	
					if (isset($posted['status'])) {				   
					   $output =  $model->itemAlias('status',$model->status); // new value for edited td
					   $data = json_encode([7=>$model->complete_reference]); // affected td index with new html at the same row
					} 
						 
					$out = json_encode(['id'=>$model->id,'output'=>$output, "data"=>$data,'message'=>'']);
				} 			
				$transaction->commit();				
			} catch (Exception $e) {
				$transaction->rollBack();
			}
									
			echo $out;
			return;
		}
		
        if ($format == 'json')
        {
			$model = [];
			foreach ($dataProvider->getModels() as $d)
			{
				$obj = $d->attributes;
				if ($arraymap)
				{					
					$map = explode(",",$arraymap);
					if (count($map) == 1)
					{
						$obj = $d[$arraymap];
					}
					else
					{
						$obj = [];					
						foreach ($map as $a)
						{
							$k = explode(":",$a);						
							$v = (count($k) > 1?$k[1]:$k[0]);
							$obj[$k[0]] = ($v == "Obj"?json_encode($d->attributes):(isset($d->$v)?$d->$v:null));
						}				
					}
				}
				
				if ($term)
				{
					if (!in_array($obj,$model))
					{
						array_push($model,$obj);
					}
				}
				else
				{	
					array_push($model,$obj);
				}
			}			
			return \yii\helpers\Json::encode($model);	
		}
		else
		{
			return $this->render('index', [
				'searchModel' => $searchModel,
				'dataProvider' => $dataProvider,
			]);
		}	
    }

    /**
     * Displays a single Order model.
     * @param integer $id
     * @additionalParam string $format
     * @return mixed
     */
    public function actionView($id,$format= false)
    {
        $model = $this->findModel($id);
        
        if ($format == 'json')
        {
			return \yii\helpers\Json::encode($model);	
		}
		else
		{
			return $this->render('view', [
				'model' => $model,
			]);
		}        
    }

    /**
     * Creates a new Order model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new Order();
		$model->time = date("Y-m-d H:i:s");	       
		$model->status = 0;
		$model->reference = "O".time();
		$model->isdel = 0;
		
		if (!Yii::$app->user->isGuest)
		{
			$model->captchaRequired = false;	
		}
		
        if (Yii::$app->request->post())        
        {
			$transaction = Yii::$app->db->beginTransaction();
			try {								
			
				$post = Yii::$app->request->post();
				$data = [];			
				if (isset($post['Order']['data']))
				{
					$data = $post['Order']['data'];
					if (isset($post['Order']['customer_id']))
					{	
						$data["customer"] = $post['Order']['customer_id'];
						//$customer = Customer::find()->where(["name"=>$data["customer"]["name"],"email"=>$data["customer"]["email"]])->one();
						$customer = Customer::find()->where("email = :email OR concat(',',phones,',') like :phone",[":email"=>$data["customer"]["email"],":phone"=>"%,".$data["customer"]["phones"].",%"])->one();
						if (!$customer)
						{
							$customer = new Customer();	
							$customer->isdel = 0;
						}
						$shipping = false;
						if (isset($data["shipping"]))
						{									
							$shipping = json_decode($data["shipping"]);					
						}
						$phones = (empty($customer->phones)?[]:explode(",",$customer->phones));
						$phones = array_unique(array_merge($phones,explode(",",$data["customer"]['phones'])));
						$addresses = array_unique(array_merge(json_decode($customer->addresses == null?"[]":$customer->addresses),array($data["customer"]['address'].", code:".($shipping?$shipping->code:""))));
						$customer->phones = implode(",",$phones);
						$customer->addresses = json_encode($addresses);
						$customer->name = $data["customer"]["name"];
						$customer->email = $data["customer"]["email"];
						if ($customer->save())
						{
							$post['Order']['customer_id'] = $customer->id;	
						}
						else
						{						
							$post['Order']['customer_id'] = null;
						}
					}
					$data["cart"] = json_encode(Yii::$app->session->get('YES_SHOPCART'));
					$post['Order']['data'] = json_encode($data);
				}				
				$model->load($post);			
				$model->log = json_encode($_SERVER);
				
				if ($model->save()) {
					Yii::$app->session->set('YES_SHOPCART',null);
					$transaction->commit();
					return $this->redirect(['view', 'id' => $model->id]);            
				} else {										
					$model->data = json_encode($data);
					$transaction->rollBack();
				}
			
			} catch (Exception $e) {
				$transaction->rollBack();
			}
		}	
        
        return $this->render('create', [
			'model' => $model,
		]);
    }

    /**
     * Updates an existing Order model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);        
		
		if (!Yii::$app->user->isGuest)
		{
			$model->captchaRequired = false;	
		}
		
        if (Yii::$app->request->post())        
        {
			$transaction = Yii::$app->db->beginTransaction();
			try {												
						
				$post = Yii::$app->request->post();
				$data = [];			
				if (isset($post['Order']['data']))
				{
					$data = $post['Order']['data'];
					if (isset($post['Order']['customer_id']))
					{	
						$data["customer"] = $post['Order']['customer_id'];
						$customer = Customer::find()->where(["name"=>$data["customer"]["name"],"email"=>$data["customer"]["email"]])->one();
						if (!$customer)
						{
							$customer = new Customer();	
							$customer->isdel = 0;
						}									
						$shipping = json_decode($data["shipping"]);					
						$phones = (empty($customer->phones)?[]:explode(",",$customer->phones));
						$phones = array_unique(array_merge($phones,explode(",",$post['Order']['customer_id']['phones'])));
						$addresses = array_unique(array_merge(json_decode($customer->addresses == null?"[]":$customer->addresses),array($post['Order']['customer_id']['address'].", code:".$shipping->code)));
						$customer->phones = implode(",",$phones);
						$customer->addresses = json_encode($addresses);
						$customer->name = $data["customer"]["name"];
						$customer->email = $data["customer"]["email"];
						if ($customer->save())
						{
							$post['Order']['customer_id'] = $customer->id;	
						}
						else
						{						
							$post['Order']['customer_id'] = null;
						}
					}
					$data["cart"] = json_encode(Yii::$app->session->get('YES_SHOPCART'));	
					$post['Order']['data'] = json_encode($data);
				}				
				$model->load($post);			
				$model->log = json_encode($_SERVER);
				
				if ($model->save()) {				
					Yii::$app->session->set('YES_SHOPCART',null);
					$transaction->commit();			
					return $this->redirect(['view', 'id' => $model->id]);            
				}
				else
				{
					$transaction->rollBack();
				}
			
			} catch (Exception $e) {
				$transaction->rollBack();
			}
		}
		else
		{	                      
			$data = json_decode($model->data);        
			$cart = json_decode($data->cart);
			Yii::$app->session->set('YES_SHOPCART',ArrayHelper::toArray($cart));
		}
        
        return $this->render('update', [
			'model' => $model,
		]);
    }

    /**
     * Deletes an existing Order model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @return mixed
     */
    public function actionDelete($id)
    {        
		$model = $this->findModel($id);        
        $model->isdel = 1;
        $model->save();
        //$model->delete(); //this will true delete
        
        return $this->redirect(['index']);
    }

    /**
     * Finds the Order model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return Order the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = Order::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }
    
	public function actionShopcart()
	{
		$result = array('status'=>0);
		if (Yii::$app->request->post())        
        {
			$post = Yii::$app->request->post();			
			$data = Yii::$app->session->get('YES_SHOPCART') == null?[]:Yii::$app->session->get('YES_SHOPCART');			
			$item = $post['shopcart'];						
			//print_r($data);
			//die();
			
			
			if (!isset($data[$item['data']['idata']]) )
			{								
				$data[$item['data']['idata']] = $item['data'];
				$result = array('status'=>1);
			}
			else
			{
				if (isset($item['data']['quantity']))
				{
					$data[$item['data']['idata']]['quantity'] = $item['data']['quantity'];
					$result = array('status'=>2);
				}
				else
				{					
					unset($data[$item['data']['idata']]);				
					$result = array('status'=>3);
				}	
			}									
			Yii::$app->session->set('YES_SHOPCART', $data);					
		}	
		return \yii\helpers\Json::encode($result);	
	}    
}
