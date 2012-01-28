<?php

require_once('api/Simpla.php');
$simpla = new Simpla();

header("Content-type: text/xml; charset=UTF-8");

// Заголовок
print
"<?xml version='1.0' encoding='UTF-8'?>
<!DOCTYPE yml_catalog SYSTEM 'shops.dtd'>
<yml_catalog date='".date('Y-m-d H:m')."'>
<shop>
<name>".$simpla->settings->site_name."</name>
<company>".$simpla->settings->company_name."</company>
<url>".$simpla->config->root_url."</url>
";

// Валюты
$currencies = $simpla->money->get_currencies(array('enabled'=>1));
$main_currency = reset($currencies);
print "<currencies>
";
foreach($currencies as $c)
if($c->enabled)
print "<currency id='".$c->code."' rate='".$c->rate_to/$c->rate_from*$main_currency->rate_from/$main_currency->rate_to."'/>
";
print "</currencies>
";


// Категории
$categories = $simpla->categories->get_categories();
print "<categories>
";
foreach($categories as $c)
{
print "<category id='$c->id'";
if($c->parent_id>0)
	print " parentId='$c->parent_id'";
print ">".htmlspecialchars($c->name)."</category>
";
}
print "</categories>
";

// Товары
$simpla->db->query("SET SQL_BIG_SELECTS=1");
// Товары
$simpla->db->query("SELECT v.price, v.id as variant_id, p.name as product_name, v.name as variant_name, p.url, p.annotation, pc.category_id, i.filename as image
					FROM __variants v LEFT JOIN __products p ON v.product_id=p.id
					
					LEFT JOIN __products_categories pc ON p.id = pc.product_id AND pc.position=(SELECT MIN(position) FROM __products_categories WHERE product_id=p.id LIMIT 1)	
					LEFT JOIN __images i ON p.id = i.product_id AND i.position=(SELECT MIN(position) FROM __images WHERE product_id=p.id LIMIT 1)	
					WHERE p.visible AND (v.stock >0 OR v.stock is NULL) GROUP BY v.id");
print "<offers>
";
 

$currency_code = reset($currencies)->code;

// В цикле мы используем не results(), a result(), то есть выбираем из базы товары по одному,
// так они нам одновременно не нужны - мы всё равно сразу же отправляем товар на вывод.
// Таким образом используется памяти только под один товар
while($p = $simpla->db->result())
{

$price = round($simpla->money->convert($p->price, $main_currency->id, false),2);
print
"
<offer id='$p->variant_id' available='true'>
<url>".$simpla->config->root_url.'/products/'.$p->url.'?variant='.$p->variant_id."</url>";
print "
<price>$price</price>
<currencyId>".$currency_code."</currencyId>
<categoryId>".$p->category_id."</categoryId>
";

if($p->image)
print "<picture>".$simpla->design->resize_modifier($p->image, 200, 200)."</picture>
";

print "<name>".htmlspecialchars($p->product_name).($p->variant_name?' '.htmlspecialchars($p->variant_name):'')."</name>
<description>".htmlspecialchars(strip_tags($p->annotation))."</description>
</offer>
";
}

print "</offers>
";
print "</shop>
</yml_catalog>
";