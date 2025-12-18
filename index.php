<?php
require __DIR__.'/config/config.php';
require __DIR__.'/app/Lib/DB.php';
require __DIR__.'/app/Lib/Auth.php';
require __DIR__.'/app/Lib/Utils.php';
require __DIR__.'/app/Lib/Theme.php';
require __DIR__.'/app/Lib/ProductSearch.php';
require __DIR__.'/app/Lib/Geo.php';
require __DIR__.'/app/Models/Settings.php';
require __DIR__.'/app/Models/User.php';
require __DIR__.'/app/Models/Category.php';
require __DIR__.'/app/Models/Product.php';
require __DIR__.'/app/Models/Page.php';
require __DIR__.'/app/Models/Metrics.php';
require __DIR__.'/app/Models/Snippet.php';
require __DIR__.'/app/Models/Live.php';
require __DIR__.'/app/Models/Review.php';
require __DIR__.'/app/Models/Customer.php';
require __DIR__.'/app/Models/Order.php';
require __DIR__.'/app/Models/Partner.php';
require __DIR__.'/app/Controllers/FrontController.php';
require __DIR__.'/app/Controllers/AdminController.php';
require __DIR__.'/app/Controllers/AuthController.php';
require __DIR__.'/app/Controllers/HelpController.php';
require __DIR__.'/app/Controllers/AiController.php';
require __DIR__.'/app/Controllers/SeoController.php';
Auth::start();
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
$base=trim(str_replace('index.php','',$_SERVER['SCRIPT_NAME']),'/');
if($base&&str_starts_with($path,'/'.$base)){$path=substr($path,strlen('/'.$base));}
$path=trim($path,'/');
$front=new FrontController();
$admin=new AdminController();
$auth=new AuthController();
if($path===''){$front->home();exit;}
if(preg_match('#^product/(.+)-(\d+)$#',$path,$m)){$front->product((int)$m[2]);exit;}
if(preg_match('#^page/(.+)-(\d+)$#',$path,$m)){$front->page((int)$m[2]);exit;}
if(preg_match('#^category/(.+)-(\d+)$#',$path,$m)){$front->category((int)$m[2]);exit;}
if($path==='search'){$front->search();exit;}
if($path==='products'){$front->products();exit;}
if($path==='categories'){$front->categoriesList();exit;}
if($path==='cart'){$front->cart();exit;}
if($path==='track/click'){$front->trackClick();exit;}
if($path==='api/products'){
 header('Content-Type: application/json; charset=utf-8');
 try{
  $cat=(isset($_GET['cat'])&&$_GET['cat']!=='')?(int)$_GET['cat']:null;
  $q=$_GET['q']??null;
  $min=$_GET['min_price']??null; $max=$_GET['max_price']??null;
  $vn=$_GET['var_name']??null; $vv=$_GET['var_value']??null;
  $limit=min(50,max(1,(int)($_GET['limit']??24)));
  $sort=$_GET['sort']??null; $offers=((string)($_GET['offers']??'')==='1');
  $items=Product::all($limit,0,$cat,$q,$min,$max,$vn,$vv,$sort,$offers);
  $out=array_map(function($p){
    $img=null; $candidates=[];
    if(!empty($p['image_thumb'])) $candidates[] = trim($p['image_thumb']);
    if(!empty($p['image_main'])) $candidates[] = trim($p['image_main']);
    foreach($candidates as $i){
      if(!$i) continue;
      if(preg_match('#^https?://#',$i)) { $img=$i; break; }
      elseif(strpos($i,'uploads/')!==false){ $img = (strpos($i,'/')===0? base_url().$i : base_url().'/'.$i); break; }
      else{ $img = base_url().'/uploads/products/'.$i; break; }
    }
    if(!$img){ $img = base_url().'/assets/favicon.ico'; }
    $varNames=[]; try{ $v=json_decode($p['variants_json']??'[]',true)?:[]; if(is_array($v)){ if(isset($v['groups'])&&is_array($v['groups'])){ foreach($v['groups'] as $g){ if(isset($g['name'])) $varNames[]=$g['name']; } } elseif(isset($v[0]['name'])){ foreach($v as $g){ $varNames[]=$g['name']; } } } }catch(\Throwable $e){}
    return [
      'id'=>(int)$p['id'],
      'name'=>$p['name'],
      'slug'=>$p['slug'],
      'price'=>(float)($p['price']??0),
      'price_regular'=>isset($p['price_regular'])?(float)$p['price_regular']:null,
      'price_offer'=>isset($p['price_offer'])&&$p['price_offer']!==null?(float)$p['price_offer']:null,
      'image'=>$img,
      'category_id'=>isset($p['category_id'])?(int)$p['category_id']:null,
      'stock'=>isset($p['stock'])?(int)$p['stock']:0,
      'variant_names'=>$varNames
    ];
  }, $items);
  echo json_encode(['ok'=>true,'items'=>$out], JSON_UNESCAPED_SLASHES);
 }catch(\Throwable $e){
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'Server error']);
 }
 exit;
}
if(preg_match('#^api/product/(\d+)$#',$path,$m)){
 header('Content-Type: application/json; charset=utf-8');
 try{
  $id=(int)$m[1]; $p=Product::find($id);
  if(!$p){ echo json_encode(['ok'=>false,'error'=>'Not found']); exit; }
  $img=null; $candidates=[]; if(!empty($p['image_thumb'])) $candidates[] = trim($p['image_thumb']); if(!empty($p['image_main'])) $candidates[] = trim($p['image_main']); foreach($candidates as $i){ if(!$i) continue; if(preg_match('#^https?://#',$i)) { $img=$i; break; } elseif(strpos($i,'uploads/')!==false){ $img = (strpos($i,'/')===0? base_url().$i : base_url().'/'.$i); break; } else { $img = base_url().'/uploads/products/'.$i; break; } } if(!$img){ $img = base_url().'/assets/favicon.ico'; }
  $items=[];
  try{
    $v=json_decode($p['variants_json']??'[]',true)?:[];
    // Try to flatten common structures
    if(isset($v['items'])&&is_array($v['items'])){
      foreach($v['items'] as $it){ $title=trim(($it['title']??($it['name']??''))); $price=$it['price']??null; $stock=$it['stock']??null; if($title!==''){ $items[]=['title'=>$title,'price'=>$price,'stock'=>$stock]; } }
    } elseif(isset($v['groups'])&&is_array($v['groups'])){
      foreach($v['groups'] as $g){ $gname=$g['name']??''; $opts=$g['options']??($g['values']??[]); if(is_array($opts)){ foreach($opts as $op){ $val=$op['value']??($op['name']??''); $price=$op['price']??null; $stock=$op['stock']??null; $title=($gname&&$val)?($val):$val; if($title!==''){ $items[]=['title'=>$title,'price'=>$price,'stock'=>$stock]; } } } }
    } elseif(is_array($v)){ foreach($v as $it){ $title=$it['title']??($it['name']??''); $price=$it['price']??null; $stock=$it['stock']??null; if($title!==''){ $items[]=['title'=>$title,'price'=>$price,'stock'=>$stock]; } } }
  }catch(\Throwable $e){}
  echo json_encode(['ok'=>true,'product'=>[
    'id'=>(int)$p['id'], 'name'=>$p['name'], 'price'=>(float)($p['price']??0), 'stock'=>(int)($p['stock']??0), 'image'=>$img,
    'variant_items'=>$items
  ]]);
 }catch(\Throwable $e){ http_response_code(500); echo json_encode(['ok'=>false,'error'=>'Server error']); }
 exit;
}
if($path==='api/order' || $path==='api/order/'){ (new FrontController())->orderCreate(); exit; }
if($path==='api/countries'){
 header('Content-Type: application/json');
 echo json_encode(['ok'=>true,'countries'=>Geo::countries(),'currencies'=>Geo::currencyCodes()], JSON_UNESCAPED_SLASHES);
 exit;
}
if($path==='ai/chat'){ (new AiController())->chat(); exit; }
if($path==='ai/seo'){ (new AiController())->seo(); exit; }
if($path==='ai/status'){ (new AiController())->status(); exit; }
if($path==='health'){ header('Content-Type: text/plain'); echo 'ok'; exit; }
if($path==='sitemap.xml'){ (new SeoController())->sitemap(); exit; }
if($path==='robots.txt'){ (new SeoController())->robots(); exit; }
if($path==='help/ask'){ (new HelpController())->ask(); exit; }
if($path==='review/submit'){ $front->reviewSubmit(); exit; }
if($path==='admin'||$path==='admin/dashboard'){if(!Auth::check()){header('Location: '.base_url().'/admin/login');exit;}$admin->dashboard();exit;}
if($path==='admin/login'){if($_SERVER['REQUEST_METHOD']==='POST'){$auth->login();}else{$auth->loginForm();}exit;}
if($path==='admin/logout'){$auth->logout();exit;}
if($path==='admin/products'){if(!Auth::check()){header('Location: '.base_url().'/admin/login');exit;}$admin->products();exit;}
if($path==='admin/products/create'){if(!Auth::check()){header('Location: '.base_url().'/admin/login');exit;}if($_SERVER['REQUEST_METHOD']==='POST'){$admin->productCreate();}else{$admin->productForm();}exit;}
if(preg_match('#^admin/products/edit/(\d+)$#',$path,$m)){if(!Auth::check()){header('Location: '.base_url().'/admin/login');exit;}if($_SERVER['REQUEST_METHOD']==='POST'){$admin->productUpdate((int)$m[1]);}else{$admin->productForm((int)$m[1]);}exit;}
if(preg_match('#^admin/products/delete/(\d+)$#',$path,$m)){if(!Auth::check()){header('Location: '.base_url().'/admin/login');exit;}$admin->productDelete((int)$m[1]);exit;}
if(preg_match('#^admin/products/toggle/(\d+)$#',$path,$m)){if(!Auth::check()){header('Location: '.base_url().'/admin/login');exit;} if($_SERVER['REQUEST_METHOD']==='POST'){ $admin->productToggle((int)$m[1]); } exit; }
if($path==='admin/categories'){if(!Auth::check()){header('Location: '.base_url().'/admin/login');exit;}if($_SERVER['REQUEST_METHOD']==='POST'){$admin->categoryCreate();}else{$admin->categories();}exit;}
if(preg_match('#^admin/categories/delete/(\d+)$#',$path,$m)){if(!Auth::check()){header('Location: '.base_url().'/admin/login');exit;}$admin->categoryDelete((int)$m[1]);exit;}
if($path==='admin/settings'){if(!Auth::check()){header('Location: '.base_url().'/admin/login');exit;}if($_SERVER['REQUEST_METHOD']==='POST'){$admin->settingsSave();}else{$admin->settings();}exit;}
if($path==='admin/ai-settings'){if(!Auth::check()){header('Location: '.base_url().'/admin/login');exit;}if($_SERVER['REQUEST_METHOD']==='POST'){$admin->aiSettingsSave();}else{$admin->aiSettings();}exit;}
if($path==='admin/theme'){if(!Auth::check()){header('Location: '.base_url().'/admin/login');exit;}if($_SERVER['REQUEST_METHOD']==='POST'){$admin->themeSave();}else{$admin->theme();}exit;}
if($path==='admin/pages'){if(!Auth::check()){header('Location: '.base_url().'/admin/login');exit;}$admin->pages();exit;}
if($path==='admin/pages/create'){if(!Auth::check()){header('Location: '.base_url().'/admin/login');exit;}if($_SERVER['REQUEST_METHOD']==='POST'){$admin->pageCreate();}else{$admin->pageForm();}exit;}
if(preg_match('#^admin/pages/edit/(\d+)$#',$path,$m)){if(!Auth::check()){header('Location: '.base_url().'/admin/login');exit;}if($_SERVER['REQUEST_METHOD']==='POST'){$admin->pageUpdate((int)$m[1]);}else{$admin->pageForm((int)$m[1]);}exit;}
if(preg_match('#^admin/pages/delete/(\d+)$#',$path,$m)){if(!Auth::check()){header('Location: '.base_url().'/admin/login');exit;}if($_SERVER['REQUEST_METHOD']==='POST'){$admin->pageDelete((int)$m[1]);}exit;}
if($path==='admin/media'){if(!Auth::check()){header('Location: '.base_url().'/admin/login');exit;}$admin->media();exit;}
if($path==='admin/media/upload'){if(!Auth::check()){header('Location: '.base_url().'/admin/login');exit;}if($_SERVER['REQUEST_METHOD']==='POST'){$admin->mediaUpload();}else{$admin->media();}exit;}
if(preg_match('#^admin/media/delete/(.+)$#',$path,$m)){if(!Auth::check()){header('Location: '.base_url().'/admin/login');exit;}if($_SERVER['REQUEST_METHOD']==='POST'){$admin->mediaDelete($m[1]);}exit;}
if($path==='admin/analytics'){if(!Auth::check()){header('Location: '.base_url().'/admin/login');exit;}if($_SERVER['REQUEST_METHOD']==='POST'){$admin->analyticsSave();}else{$admin->analytics();}exit;}
if($path==='admin/snippets'){if(!Auth::check()){header('Location: '.base_url().'/admin/login');exit;}if($_SERVER['REQUEST_METHOD']==='POST'){$admin->snippetCreate();}else{$admin->snippets();}exit;}
if($path==='admin/social'){if(!Auth::check()){header('Location: '.base_url().'/admin/login');exit;}if($_SERVER['REQUEST_METHOD']==='POST'){$admin->socialSave();}else{$admin->social();}exit;}
if($path==='admin/payments'){if(!Auth::check()){header('Location: '.base_url().'/admin/login');exit;}if($_SERVER['REQUEST_METHOD']==='POST'){$admin->paymentsSave();}else{$admin->payments();}exit;}
if($path==='admin/reviews'){if(!Auth::check()){header('Location: '.base_url().'/admin/login');exit;} $admin->reviews(); exit;}
if($path==='admin/reviews/create'){if(!Auth::check()){header('Location: '.base_url().'/admin/login');exit;} if($_SERVER['REQUEST_METHOD']==='POST'){ $admin->reviewCreate(); } else { $admin->reviewForm(); } exit;}
if(preg_match('#^admin/reviews/edit/(\d+)$#',$path,$m)){if(!Auth::check()){header('Location: '.base_url().'/admin/login');exit;} if($_SERVER['REQUEST_METHOD']==='POST'){ $admin->reviewUpdate((int)$m[1]); } else { $admin->reviewForm((int)$m[1]); } exit;}
if(preg_match('#^admin/reviews/delete/(\d+)$#',$path,$m)){if(!Auth::check()){header('Location: '.base_url().'/admin/login');exit;} $admin->reviewDelete((int)$m[1]); exit;}
if(preg_match('#^admin/reviews/approve/(\d+)$#',$path,$m)){if(!Auth::check()){header('Location: '.base_url().'/admin/login');exit;} if($_SERVER['REQUEST_METHOD']==='POST'){ $admin->reviewApprove((int)$m[1]); } exit;}
if(preg_match('#^admin/snippets/toggle/(\d+)$#',$path,$m)){if(!Auth::check()){header('Location: '.base_url().'/admin/login');exit;}if($_SERVER['REQUEST_METHOD']==='POST'){$admin->snippetToggle((int)$m[1]);}exit;}
if(preg_match('#^admin/snippets/delete/(\d+)$#',$path,$m)){if(!Auth::check()){header('Location: '.base_url().'/admin/login');exit;}if($_SERVER['REQUEST_METHOD']==='POST'){$admin->snippetDelete((int)$m[1]);}exit;}
if(preg_match('#^admin/snippets/edit/(\d+)$#',$path,$m)){if(!Auth::check()){header('Location: '.base_url().'/admin/login');exit;}if($_SERVER['REQUEST_METHOD']==='POST'){$admin->snippetUpdate((int)$m[1]);}else{$admin->snippetEdit((int)$m[1]);}exit;}
if($path==='admin/orders'){if(!Auth::check()){header('Location: '.base_url().'/admin/login');exit;}$admin->orders();exit;}
if(preg_match('#^admin/orders/status/(\d+)$#',$path,$m)){if(!Auth::check()){header('Location: '.base_url().'/admin/login');exit;} if($_SERVER['REQUEST_METHOD']==='POST'){ $admin->orderStatus((int)$m[1]); } exit; }
if(preg_match('#^admin/orders/delete/(\d+)$#',$path,$m)){if(!Auth::check()){header('Location: '.base_url().'/admin/login');exit;} if($_SERVER['REQUEST_METHOD']==='POST'){ $admin->orderDelete((int)$m[1]); } exit; }
if(preg_match('#^admin/orders/view/(\d+)$#',$path,$m)){if(!Auth::check()){header('Location: '.base_url().'/admin/login');exit;} $admin->orderView((int)$m[1]); exit; }
if(preg_match('#^admin/orders/print/(\d+)$#',$path,$m)){if(!Auth::check()){header('Location: '.base_url().'/admin/login');exit;} $admin->orderPrint((int)$m[1]); exit; }
if(preg_match('#^admin/orders/update/(\d+)$#',$path,$m)){if(!Auth::check()){header('Location: '.base_url().'/admin/login');exit;} if($_SERVER['REQUEST_METHOD']==='POST'){ $admin->orderUpdate((int)$m[1]); } exit; }
if($path==='admin/customers'){if(!Auth::check()){header('Location: '.base_url().'/admin/login');exit;}$admin->customers();exit;}
if(preg_match('#^admin/customers/view/(\d+)$#',$path,$m)){if(!Auth::check()){header('Location: '.base_url().'/admin/login');exit;} $admin->customerView((int)$m[1]); exit;}
if($path==='admin/customers/create'){if(!Auth::check()){header('Location: '.base_url().'/admin/login');exit;} if($_SERVER['REQUEST_METHOD']==='POST'){ $admin->customerCreate(); } else { $admin->customerForm(); } exit;}
if(preg_match('#^admin/customers/edit/(\d+)$#',$path,$m)){if(!Auth::check()){header('Location: '.base_url().'/admin/login');exit;} if($_SERVER['REQUEST_METHOD']==='POST'){ $admin->customerUpdate((int)$m[1]); } else { $admin->customerForm((int)$m[1]); } exit;}
if(preg_match('#^admin/customers/delete/(\d+)$#',$path,$m)){if(!Auth::check()){header('Location: '.base_url().'/admin/login');exit;} if($_SERVER['REQUEST_METHOD']==='POST'){ $admin->customerDelete((int)$m[1]); } exit;}
if(preg_match('#^admin/customers/label/(\d+)$#',$path,$m)){if(!Auth::check()){header('Location: '.base_url().'/admin/login');exit;} if($_SERVER['REQUEST_METHOD']==='POST'){ $admin->customerLabel((int)$m[1]); } exit;}
if(preg_match('#^admin/customers/assign-coupon/(\d+)$#',$path,$m)){if(!Auth::check()){header('Location: '.base_url().'/admin/login');exit;} if($_SERVER['REQUEST_METHOD']==='POST'){ $admin->customerAssignCoupon((int)$m[1]); } exit;}
if($path==='admin/marketing'){if(!Auth::check()){header('Location: '.base_url().'/admin/login');exit;}if($_SERVER['REQUEST_METHOD']==='POST'){$admin->marketingSave();}else{$admin->marketing();}exit;}
if($path==='admin/marketing/referrals'){if(!Auth::check()){header('Location: '.base_url().'/admin/login');exit;}$admin->marketingReferrals();exit;}
if($path==='admin/marketing/campaigns'){if(!Auth::check()){header('Location: '.base_url().'/admin/login');exit;}$admin->marketingCampaigns();exit;}
if($path==='admin/marketing/email'){if(!Auth::check()){header('Location: '.base_url().'/admin/login');exit;}$admin->marketingEmail();exit;}
if($path==='admin/marketing/influencers'){if(!Auth::check()){header('Location: '.base_url().'/admin/login');exit;}$admin->marketingInfluencers();exit;}
if($path==='admin/marketing/loyalty'){if(!Auth::check()){header('Location: '.base_url().'/admin/login');exit;}$admin->marketingLoyalty();exit;}
if($path==='admin/affiliate-settings'){if(!Auth::check()){header('Location: '.base_url().'/admin/login');exit;}if($_SERVER['REQUEST_METHOD']==='POST'){$admin->affiliateSettingsSave();}else{$admin->affiliateSettings();}exit;}
if($path==='admin/marketing/save'){if(!Auth::check()){header('Location: '.base_url().'/admin/login');exit;}if($_SERVER['REQUEST_METHOD']==='POST'){$admin->marketingSave();}exit;}
if($path==='admin/marketing/delete'){if(!Auth::check()){header('Location: '.base_url().'/admin/login');exit;}if($_SERVER['REQUEST_METHOD']==='POST'){$admin->marketingDelete();}exit;}
if($path==='admin/discounts'){if(!Auth::check()){header('Location: '.base_url().'/admin/login');exit;}if($_SERVER['REQUEST_METHOD']==='POST'){$admin->discountsCreate();}else{$admin->discountsList();}exit;}
if($path==='admin/discounts/new'){if(!Auth::check()){header('Location: '.base_url().'/admin/login');exit;} $admin->discounts(); exit;}
if(preg_match('#^admin/discounts/delete/([A-Za-z0-9]+)$#',$path,$m)){if(!Auth::check()){header('Location: '.base_url().'/admin/login');exit;}if($_SERVER['REQUEST_METHOD']==='POST'){$admin->discountsDelete($m[1]);}exit;}
if($path==='admin/markets'){if(!Auth::check()){header('Location: '.base_url().'/admin/login');exit;}$admin->markets();exit;}
if($path==='admin/help'){if(!Auth::check()){header('Location: '.base_url().'/admin/login');exit;} if($_SERVER['REQUEST_METHOD']==='POST'){ $admin->helpAdd(); } else { $admin->help(); } exit;}
if(preg_match('#^admin/help/delete/(\d+)$#',$path,$m)){ if(!Auth::check()){header('Location: '.base_url().'/admin/login');exit;} if($_SERVER['REQUEST_METHOD']==='POST'){ $admin->helpDelete((int)$m[1]); } exit; }
if($path==='admin/manual'){if(!Auth::check()){header('Location: '.base_url().'/admin/login');exit;}$admin->manual();exit;}
if($path==='admin/inventory'){if(!Auth::check()){header('Location: '.base_url().'/admin/login');exit;}if($_SERVER['REQUEST_METHOD']==='POST'){$admin->inventorySave();}else{$admin->inventory();}exit;}
if(preg_match('#^admin/inventory/stock/(\d+)$#',$path,$m)){if(!Auth::check()){header('Location: '.base_url().'/admin/login');exit;} if($_SERVER['REQUEST_METHOD']==='POST'){ $admin->inventorySaveOne((int)$m[1]); } exit; }
if($path==='admin/homepage'){if(!Auth::check()){header('Location: '.base_url().'/admin/login');exit;} if($_SERVER['REQUEST_METHOD']==='POST'){ $admin->homepageSave(); } else { $admin->homepage(); } exit;}
// Media Library API
if($path==='admin/media/api/list'){if(!Auth::check()){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'auth']);exit;}$admin->mediaApiList();exit;}
if($path==='admin/media/api/upload'){if(!Auth::check()){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'auth']);exit;}if($_SERVER['REQUEST_METHOD']==='POST'){$admin->mediaApiUpload();}exit;}
if($path==='admin/media/api/update'){if(!Auth::check()){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'auth']);exit;}if($_SERVER['REQUEST_METHOD']==='POST'){$admin->mediaApiUpdate();}exit;}
if($path==='admin/media/api/delete'){if(!Auth::check()){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'auth']);exit;}if($_SERVER['REQUEST_METHOD']==='POST'){$admin->mediaApiDelete();}exit;}
if($path==='join-partner'){ $front->joinPartner(); exit; }
if($path==='affiliate/register'){ if($_SERVER['REQUEST_METHOD']==='POST'){ $front->affiliateRegisterSubmit(); } else { $front->affiliateRegister(); } exit; }
if($path==='affiliate/dashboard'){ $front->affiliateDashboard(); exit; }
if($path==='affiliate/withdraw'){ if($_SERVER['REQUEST_METHOD']==='POST'){ $front->affiliateWithdraw(); } exit; }
http_response_code(404);
echo 'Not Found';
