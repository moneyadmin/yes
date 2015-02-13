<?php

namespace amilna\yes\controllers;

use Yii;
use amilna\yes\models\Product;
use amilna\yes\models\ProductSearch;
use amilna\yes\models\CatPro;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;

/**
 * ProductController implements the CRUD actions for Product model.
 */
class ProductController extends Controller
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
	
    /**
     * Lists all Product models.
     * @params string $format, array $arraymap, string $term
     * @return mixed
     */
    public function actionIndex($format= false,$arraymap= false,$term = false)
    {
        $searchModel = new ProductSearch();        
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams+($term?['ProductSearch'=>[$arraymap=>$term]]:[]));

        if ($format == 'json')
        {
			$model = [];
			foreach ($dataProvider->getModels() as $d)
			{
				$obj = $d->attributes;
				if ($arraymap)
				{
					$map = explode(",",$arraymap);
					if (count($map) == 1 || $term)
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
							$obj[$k[0]] = (isset($d[$v])?$d[$v]:null);
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

    public function actionAdmin($format= false,$arraymap= false,$term = false)
    {
        $searchModel = new ProductSearch();        
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams+($term?['ProductSearch'=>[$arraymap=>$term]]:[]));

        if ($format == 'json')
        {
			$model = [];
			foreach ($dataProvider->getModels() as $d)
			{
				$obj = $d->attributes;
				if ($arraymap)
				{
					$map = explode(",",$arraymap);
					if (count($map) == 1 || $term)
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
							$obj[$k[0]] = (isset($d[$v])?$d[$v]:null);
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
			return $this->render('admin', [
				'searchModel' => $searchModel,
				'dataProvider' => $dataProvider,
			]);
		}	
    }

    /**
     * Displays a single Product model.
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
	
	public function actionAdd()
	{
		$result = array('status'=>0);
		if (Yii::$app->request->post())        
        {
			$post = Yii::$app->request->post();			
			$data = Yii::$app->session->get('YES_SHOPCART') == null?[]:Yii::$app->session->get('YES_SHOPCART');			
			$item = $post['shopcart'];
			if (!isset($data[$item['data']['id']]) )
			{				
				$data[$item['data']['id']] = $item['data'];				
			}
			else
			{
				if (isset($item['data']['quantity']))
				{
					$data[$item['data']['id']]['quantity'] = $item['data']['quantity'];
				}
				else
				{					
					unset($data[$item['data']['id']]);				
				}	
			}									
			Yii::$app->session->set('YES_SHOPCART', $data);						
			$result = array('status'=>1,'data'=>$data);			
		}	
		return \yii\helpers\Json::encode($result);	
	}
	
    /**
     * Creates a new Product model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new Product();
		$model->time = date("Y-m-d H:i:s");	
        $model->author_id = Yii::$app->user->id;        

        if (Yii::$app->request->post())        
        {
			$post = Yii::$app->request->post();						
			$category = [];
			$data = [];
			$images = [];
			if (isset($post['Product']['category']))
			{
				$category = $post['Product']['category'];
			}
			if (isset($post['Product']['data']))
			{
				$data = $post['Product']['data'];
				$post['Product']['data'] = json_encode($data);
			}	
			if (isset($post['Product']['images']))
			{
				$images = $post['Product']['images'];
				$post['Product']['images'] = json_encode($images);			
			}	
			$model->load($post);			
			
			if ($model->save()) {
				
				$cs = CatPro::deleteAll("product_id = :id",["id"=>$model->id]);
				
				foreach ($category as $d)
				{
					$c = CatPro::find()->where("product_id = :id AND category_id = :aid",["id"=>$model->id,"aid"=>intval($d)])->one();
					if (!$c)
					{
						$c = new CatPro();	
					}					
					$c->product_id = $model->id;
					$c->category_id = $d;
					$c->isdel = 0;					
					$c->save();								
				}
								
				return $this->redirect(['view', 'id' => $model->id]);            
			} else {
				$model->id = array(
							'category'=>array_merge($category,[]),							
						);	
						
				$model->images = $images;
				$model->data = json_encode($data);
			}
		}	
        
        return $this->render('create', [
			'model' => $model,
		]);
    }

    /**
     * Updates an existing Product model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);
        
		if (Yii::$app->request->post())        
        {
			$post = Yii::$app->request->post();
			$category = [];
			$data = [];
			$images = [];
			if (isset($post['Product']['category']))
			{
				$category = $post['Product']['category'];
			}
			if (isset($post['Product']['data']))
			{
				$data = $post['Product']['data'];
				$post['Product']['data'] = json_encode($data);
			}	
			if (isset($post['Product']['images']))
			{
				$images = $post['Product']['images'];
				$post['Product']['images'] = json_encode($images);			
			}	
			$model->load($post);
			
			if ($model->save()) {
				
				$cs = CatPro::deleteAll("product_id = :id",["id"=>$model->id]);
				
				foreach ($category as $d)
				{
					$c = CatPro::find()->where("product_id = :id AND category_id = :aid",["id"=>$model->id,"aid"=>intval($d)])->one();
					if (!$c)
					{
						$c = new CatPro();	
					}					
					$c->product_id = $model->id;
					$c->category_id = $d;
					$c->isdel = 0;					
					$c->save();								
				}
								
				return $this->redirect(['view', 'id' => $model->id]);            
			} 
		}	                
                
        $model->images = json_decode($model->images);
        
        return $this->render('create', [
			'model' => $model,
		]);
    }

    /**
     * Deletes an existing Product model.
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
        
        return $this->redirect(['admin']);
    }

    /**
     * Finds the Product model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return Product the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = Product::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }
}