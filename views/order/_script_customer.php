<?php

use yii\helpers\Url;

$module = Yii::$app->getModule("yes");
?>
<script type="text/javascript">
	
<?php $this->beginBlock('CUSTOMER') ?>			

	function findCustomer()
	{
		var name = $("#order-customer_id-name").val();
		var email = $("#order-complete_reference-email").val();
		var phones = $("#order-customer_id-phones").val();
		name = (name==""?"false":name);
		email = (email==""?"":email);
		phones = (phones==""?"false":phones);
						
		var val = {"name":name,"email":email,"phones":phones,"format":"json","term":name};
		
		var url = "<?= Yii::$app->urlManager->createUrl('//yes/customer/search')?>";
		var data = val;				
		var ok = function(json)
					{																	
						json = jQuery.parseJSON(json);
						if (json.length	> 0)
						{
							json = json[0];
							$("#order-customer_id-name").val(json.name);	
							$("#order-customer_id-email").val(json.email);															
							//$("#order-customer_id-phones").val(json.phones);
							var html = "";
							var addrs = JSON.parse(json.addresses);							
							for (a in addrs)
							{
								html += "<a title=\""+addrs[a]+"\" class=\"btn btn-sm btn-default address-add\" style=\"max-width:100px;text-overflow: ellipsis;\">"+addrs[a].substr(0,10)+"...</a>";								
							}				
							
							var addr = addrs[0];
							if (addrs[0].indexOf(", code:") >= 0)
							{
								var code = addrs[0].substr(addrs[0].lastIndexOf(", code:")+7);
								addr = addrs[0].substr(0,addrs[0].lastIndexOf(", code:"));
								findShip(code);
							}										
							$("#order-customer_id-address").val(addr);							
							
							$(".list-address").html(html);
							$(".address-add").click(function(){
								var ad = $(this).attr("title");								
								var addr = ad;
								if (ad.indexOf(", code:") >= 0)
								{
									var code = ad.substr(ad.lastIndexOf(", code:")+7);
									addr = ad.substr(0,ad.lastIndexOf(", code:"));
									findShip(code);
								}
								$("#order-customer_id-address").val(addr);								
							});
							
						}						
					};
		
		var err = function()
					{											
					};
		
		ajaxPost(url,data,ok,err);
	}


	$("#order-customer_id-email,#order-customer_id-phones").change(function(){
		findCustomer();
	});
	
<?php $this->endBlock(); ?>

</script>
<?php
yii\web\YiiAsset::register($this);
$this->registerJs($this->blocks['CUSTOMER'], yii\web\View::POS_END);
