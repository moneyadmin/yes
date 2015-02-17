<?php

use yii\helpers\Html;
use yii\widgets\DetailView;
use amilna\yes\models\Payment;

/* @var $this yii\web\View */
/* @var $model amilna\yes\models\Order */

$this->title = 'Invoice '.$model->reference;
$this->params['breadcrumbs'][] = ['label' => Yii::t('app', 'Orders'), 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="order-view">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a(Yii::t('app', 'Update'), ['update', 'id' => $model->id], ['class' => 'btn btn-primary']) ?>
        <?= Html::a(Yii::t('app', 'Delete'), ['delete', 'id' => $model->id], [
            'class' => 'btn btn-danger',
            'data' => [
                'confirm' => Yii::t('app', 'Are you sure you want to delete this item?'),
                'method' => 'post',
            ],
        ]) ?>
    </p>

    <?php
    /*
    echo DetailView::widget([
        'model' => $model,
        'attributes' => [
            'id',
            'customer_id',
            'reference',
            'total',
            'data:ntext',
            'status',
            'time',
            'complete_reference',
            'complete_time',
            'log:ntext',
            'isdel',
        ],
    ]); */
    
    $data = json_decode($model->data);
    $customer = $data->customer;
    $shipping = isset($data->shipping)?json_decode($data->shipping):null;
    $cart = isset($data->cart)?json_decode($data->cart):null;
    $payment = Payment::findOne($data->payment);
    
    $module = Yii::$app->getModule("yes");
    
    //print_r($module->company);
    //die();
    ?>

	<style>
		table,tr,td,th{
			border : 1px solid;	
			padding :10px;		
			border-collapse: collapse;
		}
		.table {		
			margin:auto;	
		}
	</style>
	<div id="invoice" style="overflow:auto;">	
	<table class="table table-bordered">
		<tr>		
			<td colspan=3>			
				<h3><?= 'Invoice '.$model->reference?> <!--<small class="pull-right"><?= $model->itemAlias("status",$model->status)?></small>--></h3>
				<?= Yii::t("app","date ").date('r',strtotime($model->time))?>				
			</td>
			<td colspan=3>
				<!--<img id="logod" src="" style="display:block;margin:auto;height:60%;margin-top:5%;">-->
				<small class="btn btn-warning"><?= $model->itemAlias("status",$model->status)?></small>
			</td>
		</tr>
		<tr>
			<td colspan=3>
				<h4><?= Yii::t("app","Invoice to")?></h4>
				<address>
					<strong><?= $customer->name?></strong><br>
					<?= $customer->address?><br>
					<?php 
					if ($shipping != null)
					{						
						echo $shipping->city.', '.$shipping->area.'<br>';					
					}				
					?>
					<abbr title="Phone">P: </abbr>
					<?= $customer->phones?><br>
					<a href="mailto:<?= $model->toHex($customer->email)?>"><?= str_replace("@"," [AT] ",$customer->email)?></a>
				</address>
				<br>
				<strong><?= Yii::t("app","for")?></strong><br>
				<?= Yii::t("app","Products buying in this website, payment via")?>				
				<?= $payment->terminal."<br>".Yii::t("app","account ").$payment->account." ".Yii::t("app","in the name of ").$payment->name?>
			</td>
			<td colspan=3>
				<h4><?= Yii::t("app","Published by")?></h4>
				<address>
					<strong><?= $module->company["name"]?></strong>
					<br>
					<?= $module->company["address"]?>
					<br>
					<abbr title="Phone">P: </abbr>
					<?= $module->company["phone"]?>	
					<br>					
					<a href="mailto:<?= $model->toHex($module->company["email"])?>"><?= str_replace("@"," [AT] ",$module->company["email"])?></a>
				</address>				
			</td>
		</tr>
		<tr>
			<td colspan=6><h4><?= Yii::t("app","Products Details")?></h4></td>
		</tr>
		<tr><th>No</th><th><?= Yii::t("app","Title")?></th><th><?= Yii::t("app","Remarks")?></th><th style="text-align:right"><?= Yii::t("app","Quantity")?></th><th style="text-align:right;min-width:120px;"><?= Yii::t("app","Unit Price")?></th><th style="text-align:right;min-width:120px;"><?= Yii::t("app","Total")?></th></tr>
		
		<?php
						
			$n = 0;		
			foreach ($cart as $p)
			{				
				$n += 1;
				$title = $p->title;
				$remarks = "";
				foreach ($p as $k=>$v)
				{
					if (substr($k,0,5) == "data_")
					{
						$remarks .= ($remarks == ""?"":", ").substr($k,5).": ".$v;
					}
				}																
				
				echo '<tr><td>'.$n.'</td><td>'.$title.'</td><td>'.$remarks.'</td><td style="text-align:right">'.$p->quantity.'</td><td style="text-align:right">'.$model->toMoney($p->price,0).'</td><td style="text-align:right">'.$model->toMoney($p->quantity*$p->price,0).'</td></tr>';
			}
			
			if ($shipping != null)
			{				
				echo '<tr><td>'.($n+1).'</td><td>'.Yii::t("app","Shipping Cost").'</td><td><i>'.$shipping->provider.' '.$shipping->code.'</i> '.Yii::t("app","destination ").$shipping->city.' ('.$shipping->area.')</td><td style="text-align:right"></td><td style="text-align:right">'.$model->toMoney($shipping->cost,0).'</td><td style="text-align:right">'.$model->toMoney($data->shippingcost,0).'</td></tr>';
			}
			
			if ($data->vat > 0)
			{				
				echo '<tr><td>'.($n+1).'</td><td>'.Yii::t("app","VAT").'</td><td>'.Yii::t("app","Value Added Tax").'</td><td style="text-align:right"></td><td style="text-align:right">'.($module->defaults["vat"]*100).'%</td><td style="text-align:right">'.$model->toMoney($data->vat,0).'</td></tr>';
			}
			
			echo '<tr><th colspan=5 >Total</th><th style="text-align:right">'.$model->toMoney($model->total,0).'</th></tr>';
		
		?>
		<tr>
			<th colspan=2><?= Yii::t("app","Order Remarks")?></th>
			<td colspan=4><?= $data->note?></td>
		</tr>
	</table>

	</div>

	<div class="no-print">
	<p align="center"  ><a style="cursor:pointer;text-decoration:none;" onclick="window.print();return false;" ><?= Yii::t("app","Print")?></a>
	</p>			
	</div>

</div>