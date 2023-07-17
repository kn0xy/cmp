<?php
session_name('ChevyMarketingPromo');
session_start();
// Initialize connection with MySQL Database
include('db.php');
$pageId = 'Chevy';
$logoImg = "assets/img/cmp_header_02.jpg";

function searchForId($match, $array) {
   $theMatch = explode('-', $match);
   $yr = $theMatch[0];
   $mdl = $theMatch[1];
   foreach ($array as $key => $val) {
       if ($val['year'] == $yr && $val['model'] == $mdl) {
           return $key;
       }
   }
   return null;
}

if(isset($_GET['enterZip'])) {
    $zip = $_GET['enterZip'];
    $_SESSION['userZip'] = $zip;
    // First, search database cache to see if ZIP code has been assigned to a dealer
    $searchZip = mysqli_query($knoxyConn, "SELECT dealerid FROM zips WHERE zip=$zip") or die(mysqli_error($knoxyConn));
    if(mysqli_num_rows($searchZip) > 0) {
        $szr = mysqli_fetch_assoc($searchZip);
        $dealerId = $szr['dealerid'];
        $_SESSION['prefDlr'] = $dealerId;
    } else {
    // If no record exists for the entered ZIP, perform a lookup and store the result    
        $getDealers = mysqli_query($knoxyConn, "SELECT id,zip FROM dealers");
        $shortestDistance = 9999.99;
        $shortestId = -1;
        while($dealers = mysqli_fetch_assoc($getDealers)) {
            $dealerZip = $dealers['zip'];
            // use the ZipCodeAPI.com service to determine the dealership with shortest distance from the user 
            $apiKey = 'ySiXR1f0avRxaxtQWbM4oVxFXtInfebO7tLYu1ZExRcHoGylSkpggXWdqFBGUMv1';
            $apiUrl = "https://www.zipcodeapi.com/rest/$apiKey/distance.json/$zip/$dealerZip/mile";
            $apiCurl = curl_init();
            curl_setopt($apiCurl, CURLOPT_URL, $apiUrl);
            curl_setopt($apiCurl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($apiCurl, CURLOPT_SSL_VERIFYPEER, false);
            $apiJson = curl_exec($apiCurl);
            curl_close($apiCurl);
            $apiObj = json_decode($apiJson);
            $apiDistance = $apiObj->distance;
            if($apiDistance < $shortestDistance) {
                $shortestDistance = $apiDistance;
                $shortestId = $dealers['id'];
            }
        }
        // Save the mapping and continue
        mysqli_query($knoxyConn, "INSERT INTO zips (zip, dealerid) VALUES ($zip, $shortestId)") or die(mysqli_error($knoxyConn));
        $_SESSION['prefDlr'] = $shortestId;
    }
    echo 'success';
exit();
}

if(isset($_GET['changeLoc'])) {
    session_unset();
    echo 'success';
exit();
}

if(isset($_GET['certAppend'])) {
    $usrAddress = $_POST['address'];
    $usrPermalink = $_POST['pl'];
    mysqli_query($knoxyConn, "UPDATE certs SET address='$usrAddress' WHERE permalink='$usrPermalink'") or die(mysqli_error($knoxyConn));
    echo 'success';
exit();
}

if(isset($_SESSION['userZip'])) {
include('assets/include.php');   
// Gather information from database about our list of cars and store it in an array
$dq = "SELECT dealer,showncars,state,ppurl FROM dealers WHERE id=".$_SESSION['prefDlr'];
$dealerQuery = mysqli_query($knoxyConn,$dq) or die(mysqli_error($knoxyConn));
$userDealer = mysqli_fetch_assoc($dealerQuery);
$dealerState = $us_state_names[$userDealer['state']];
$shownCars = json_decode($userDealer['showncars']);
$promoDealer = $userDealer['dealer'];
$dlrPrivacyPolicy = $userDealer['ppurl'];
$cq = "SELECT * FROM cars WHERE make='".$pageId."' ORDER BY year DESC, model";
$carQuery = mysqli_query($knoxyConn,$cq) or die(mysqli_error($knoxyConn));
$cars = array();
while($row = mysqli_fetch_array($carQuery)) {
    $carId = (int) $row['id'];
    if(array_search($carId, $shownCars) !== false) {
        $tcdc = json_decode($row['dealer_cash']);
        foreach($tcdc as $dc) {
            $did = (int) $dc->dealer;
            if($did == $_SESSION['prefDlr']) {
                $row['daui'] = (int) $dc->daui;
                break;
            }
        }
        array_push($cars, $row);
    }
}
// Determine highest possible discount
$highestPossibleDiscount = 0;
foreach($cars as $hpd) {
    $hpdDaui = 0;
    $hpdc = json_decode($hpd['dealer_cash']);
    foreach($hpdc as $dc) {
        $did = (int) $dc->dealer;
        if($did == $_SESSION['prefDlr']) {
            $hpdDaui = (int) $dc->daui;
            break;
        }
    }
    if($hpdDaui > $highestPossibleDiscount) $highestPossibleDiscount = $hpdDaui;
}

if(isset($_GET['vehicleInfo'])) {
    $vehicle = $_GET['vehicleInfo'];
    // get vehicle trims & colors
    $colors = array();
    $trims = array();
    $vcq = mysqli_query($knoxyConn,"SELECT * FROM colors WHERE vehicle='$vehicle'") or die(mysqli_error($knoxyConn));
    while($color = mysqli_fetch_array($vcq)) {
        array_push($colors, $color);
    }
    $ntq = mysqli_query($knoxyConn,"SELECT trim FROM trims WHERE vehicle='$vehicle'") or die(mysqli_error($knoxyConn));
    while($nt = mysqli_fetch_assoc($ntq)) {
        array_push($trims,$nt['trim']);
    }
    $echoArray = array();
    $carRowId = searchForId($vehicle, $cars);
    array_push($echoArray, $cars[$carRowId]);
    array_push($echoArray, $colors);
    array_push($echoArray, $trims);
    $response = json_encode($echoArray);
    echo $response;
exit();
}
if(isset($_GET['vehicleFeatures'])) {
    $fVoi = $_GET['vehicleFeatures'];
    $fData = array();
    // get trims
    $fgtq = mysqli_query($knoxyConn, "SELECT trim FROM trims WHERE vehicle='$fVoi'") or die(mysqli_error($knoxyConn));
    while($tTrim = mysqli_fetch_assoc($fgtq)) {
        $ftTrim = $tTrim['trim'];
        // get features based on trims
        $ftSt = str_replace('.', '', $ftTrim);
        $ftStr = str_replace(' ', '-', $ftSt);
        $fthq = mysqli_query($knoxyConn, "SELECT features FROM features WHERE vehicle='$fVoi' AND trim='$ftStr'") or die(mysqli_error($knoxyConn));
        $ftHtml = '';
        while($fth = mysqli_fetch_assoc($fthq)) {
            $ftHtml .= $fth['features']; 
        }
        // organize data
        $oData = array('trim'=>$ftStr, 'pub'=>$ftTrim, 'features'=>$ftHtml);
        array_push($fData, $oData);
    }
    if(mysqli_num_rows($fgtq) < 1) {
        $fthq = mysqli_query($knoxyConn, "SELECT features FROM features WHERE vehicle='$fVoi' AND trim='$fVoi'") or die(mysqli_error($knoxyConn));
        $ftHtml = '';
        while($fth = mysqli_fetch_assoc($fthq)) {
            $ftHtml .= $fth['features']; 
        }
        // get highest cash discount for this trim
        $thpd = 0;
        $ftdq = mysqli_query($knoxyConn, "SELECT totalcash FROM incentives WHERE vehicle='$fVoi'") or die(mysqli_error($knoxyConn));
        while($ftd = mysqli_fetch_assoc($ftdq)) {
            $thisFtd = (int) $ftd['totalcash'];
            if($thisFtd > $thpd) $thpd = $thisFtd;
        }
        // organize data
        $fPub = str_replace('-',' ',$fVoi);
        $oData = array('trim'=>$fVoi, 'pub'=>$fPub, 'features'=>$ftHtml, 'discount'=>$thpd);
        array_push($fData, $oData);
    }
    // send organized data to client
    echo json_encode($fData);
    exit();
}
}
?>
<!DOCTYPE html>
<!--[if IE 8 ]><html class="ie ie8" lang="en"> <![endif]-->
<!--[if (gte IE 9)|!(IE)]><!--><html lang="en"> <!--<![endif]-->
<head>
	<meta charset="utf-8">
	<title>Chevy Marketing Promotion</title>
	<meta name="description" content="chevy marketing promo promotion chevrolet">
	<meta name="author" content="Knoxy" >

	<!-- Mobile Specific Metas
  	================================================== -->
	<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">

	<!-- CSS Stylesheets
  	================================================== -->
        <link rel="icon" type="image/x-icon" href="assets/favicon.ico" />
	<link rel="stylesheet" type="text/css" href="assets/css/style.css" />
        <link rel="stylesheet" type="text/css" href="assets/css/flexslider.css" />
        <link rel="stylesheet" type="text/css" href="assets/css/ui-lightness/jquery-ui-1.10.3.custom.min.css" />

     <!-- Javascripts 
	================================================== -->
    <script src="assets/js/jquery-1.10.2.js"></script>
	<script src="assets/js/respond.min.js"></script>
        <script src="assets/js/jquery.flexslider-min.js"></script>
        <script src="assets/js/jquery.validate.min.js"></script>
        <script src="assets/js/jquery.maskedinput.min.js"></script>
        <script src="assets/js/jquery-ui-1.10.4.custom.min.js"></script>
        <script>function initMap(){}</script>
        <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyDYMCLzEhpmekLV4_je_REMdfPtzGXiyh0&callback=initMap&libraries=geometry"></script>
        <script src="assets/js/gmaps.js"></script>
        <script src="assets/js/functions.js"></script>
        
    
    
                
</head>
<body onload="bannerLoad();">
    <header id="header-global" role="banner" style="background: #FFF;"> 
       <div class="container">
          <div class="row">
            <div id="logo"><img src="<?php echo $logoImg;?>" /></div>
          </div>
        </div>
    </header>
    <div id="main">
        <section id="page-feature-image">
          <div class="row">
            <div class="container">
              <div class="banner">
                <ul class="slides">
                  <li><img class="imgSlide" src="assets/img/gmp.jpg" /></li>
                  
                </ul>
              </div>
            </div>
          </div>
        </section>	
        <section id="form-content">
            <div class="container" id="contentWrapper">
                <header class="section-heading row">
                    <div id="gmPromo"><span>GENERAL &nbsp; MOTORS &nbsp; PROMOTION</span></div>
                    <div id="prePage">
                      <?php if(!isset($_SESSION['userZip'])) {?>
                        <h2 style="margin-top:10px"><span style="color: #0066cc">Step 1:</span> Location</h2>
                          <div style="float:right;width:40%;margin:10px">
                              <p><strong>Why we ask for your location:</strong></p>
                              <br clear="both">
                              <p style="text-align:left">Your location is used to find the closest participating dealer, as well as to find the most 
                                  up-to-date manufacturer incentives and rebates available in your area.</p>
                          </div>
                        <div id="location">
                            <p class="ajaxLoading" style="margin-left:50px;float:none;text-align:left">Detecting location...</p>
                            <p id="step1or">OR</p>   
                            <p id="step1manualZip">Enter your zip code: 
                                <input type="text" id="userZip" style="width:60px;display:inline" maxlength="5">
                                &nbsp; <button type="button" id="okBtn">OK</button>
                            </p>
                        </div>
                        <script>
                            $('#okBtn').button({disabled:true}).addClass('ui-state-disabled');
                            GMaps.geolocate({
                                success: function(position) {
                                  var lat = position.coords.latitude;
                                  var lng = position.coords.longitude;
                                  GMaps.geocode({
                                      lat: lat,
                                      lng: lng,
                                      callback: function(results, status) {
                                          
                                          if(status === 'OK') {
                                              var lc = results[0].address_components;
                                              var city, state, zip;
                                              for(var c=0;c<lc.length;c++) {
                                                  var componentType = lc[c].types[0];
                                                  if(!city) {
                                                      if(componentType==='locality' || componentType==='administrative_area_level_3') {
                                                          city = lc[c].long_name;
                                                          continue;
                                                      }
                                                  }
                                                  if(!state) {
                                                      if(componentType==='administrative_area_level_1') {
                                                          state = lc[c].short_name;
                                                          continue;
                                                      }
                                                  }
                                                  if(!zip) {
                                                      if(componentType==='postal_code') {
                                                          zip = lc[c].short_name;
                                                          continue;
                                                      }
                                                  }
                                              }
                                              var loc = city+', <span id="locState">'+state+'</span> '+zip;
                                              $('#location').children('p:nth-of-type(1)').removeClass('ajaxLoading').css('margin-left','20px')
                                                      .html('<span style="font-style:italic">Detected location:</span> <strong>'+loc+'</strong>')
                                                      .append('<br><button type="button" id="glcBtn">Continue</button>&nbsp; if this location is correct.<br><br>');
                                              $('#glcBtn').button();
                                          }
                                      }
                                  });
                                },
                                error: function(error) {
                                    $('#location').children('p:nth-of-type(1)').fadeOut();
                                    $('#step1or').fadeOut();
                                    console.log(error);
                                },
                                not_supported: function() {
                                    $('#location').children('p:nth-of-type(1)').fadeOut();
                                    $('#step1or').fadeOut();
                                }
                            });

                            $('#okBtn').on('click', function() {
                                var zip = $('#userZip').val();
                                if(zip.length < 5) {
                                    alert('That is not a valid ZIP code!');
                                    return false;
                                }
                                enterZip(zip);
                            });
                            
                            $('body').on('click', '#glcBtn', function() {
                                var locText = $('#location').children('p:nth-of-type(1)').children('strong').text();
                                var zip = locText.substring(locText.length-5);
                                enterZip(zip);
                            });
                            
                            $('#userZip').on('keyup', function(e) {
                                if($(this).val().length === 5) {
                                    $('#okBtn').button("enable").removeClass('ui-state-disabled');
                                    if(e.keyCode === 13) $('#okBtn').click();
                                } else {
                                    $('#okBtn').button("disable").addClass('ui-state-disabled');
                                }
                            });
                            
                            function enterZip(zipCode) {
                                var dlg = document.createElement('div');
                                $(dlg).html('<p class="ajaxLoading" style="margin:20px">Finding your preferred promotional dealer...</p>').dialog({
                                    modal: true,
                                    draggable: false,
                                    resizable: false,
                                    open: function() {
                                        $(dlg).parent().children('.ui-dialog-titlebar').remove();
                                    }
                                });
                                $.ajax({
                                    type: "GET",
                                    url: "index.php?enterZip="+zipCode,
                                    success: function(result) {
                                        if(result==='success') {
                                            location.reload(false);
                                        }
                                    }
                                });
                            }
                        </script>
                      <?php } else {?>
                      <div class="eight columns" id="welcomeText">
                        <div class="hideMe">
                            <img src="assets/img/findnewroads.png" id="findNewRoads">
                            <h1>WELCOME ALL <span id="welcomeState"><?php echo strtoupper($dealerState);?></span> RESIDENTS</h1>
                        </div>
                        <p>You are only seconds away from receiving your very own personalized certificate valid for up to
                            <strong><span id="maxDaui">$<?php echo number_format($highestPossibleDiscount, 0, '.', ',');?> OFF!</span></strong></p>
                      </div>
                      <div class="formWrapper">
                        <form id="certData" method="get" action="">
                          <input type="hidden" id="dealerId" name="dealerId" value="<?php echo $_SESSION['prefDlr'];?>">
                          <input type="hidden" id="certZip" name="certZip" value="<?php echo $_SESSION['userZip'];?>">
                          <div class="formRow" id="voiRow">  
                            <div class="left">Vehicle of Interest:</div>
                            <div class="right">
                              <select id="voi" onchange="carDetails(this.value);" name="voi" required>
                                <option value="X" selected="selected" disabled="disabled">Please select a vehicle of interest</option>
                                <?php
                                    $crntYr = 2015;
                                    for($i=0;$i < count($cars);$i++) {
                                        $cYear = $cars[$i]['year'];
                                        $cMake = $cars[$i]['make'];
                                        $cModel = $cars[$i]['model'];
                                        $cDaui = $cars[$i]['daui'];
                                        $optStr = $cYear . ' ' . $cMake . ' ' . $cModel . ' - up to $' . number_format($cDaui) . ' off';
                                        if($cYear != $crntYr) {
                                            echo '<option value="separator" disabled="disabled">─────────────────────────</option>';
                                            $crntYr = $cYear;
                                        }
                                        echo '<option value="'.$cYear.'-'.$cModel.'">'.$optStr.'</option>'.PHP_EOL;
                                    }
                                ?>
                              </select>
                                <span style="font-style:italic;font-size:0.7em" id="mVoi"></span>
                              <input type="hidden" id="voiColor" name="voiColor" value="NA">
                              <input type="hidden" id="colorTitle" name="colorTitle" value="NA">
                              <input type="hidden" id="dealerCash" name="dealerCash" value="0">
                            </div>
                          </div> 
                          <div class="formRow">
                            <div class="left">First Name:</div>
                            <div class="right"><input type="text" id="fname" style="width:70%" name="fname" minlength="2" type="text" required></div>
                          </div>
                          <div class="formRow">
                            <div class="left">Last Name:</div>
                            <div class="right"><input type="text" id="lname" style="width:70%" name="lname" minlength="2" type="text" required></div>
                          </div>		
                          <div class="formRow">
                            <div class="left">Email:</div>
                            <div class="right"><input type="text" id="email" name="email" type="email" required></div>
                          </div>	
                          <div class="formRow">
                            <div class="left">Phone:</div>
                            <div class="right"><input class="customphone" style="width:112px" type="text" id="phone" name="phone" placeholder="___-___-____" required></div>
                          </div>
                          <div class="formRow">
                            <div class="left">Promotional<br>Dealer:</div>
                            <div class="right" style="font-size:1.2em;font-style:italic;padding-top:4px"><?php echo $promoDealer;?>
                                <br><span style="font-size:0.8em"><a href="#" onclick="changeLoc()">Change</a></span>
                            </div>
                          </div>  
                          <div class="formRow">
                            <input id="genBtn" class="submit" type="submit" value="Continue to Your Certificate">
                          </div>  
                        </form>
                      </div>
                    
                      <div id="carDetails">
                         <div id="carImage">
                             <div id="colorBar"></div>
                         </div>
                         <div id="cdWrap">
                             <div id="cdf1" class="cdf"></div>
                             <div id="cdf2" class="cdf"></div>
                             <div id="cdf3" class="cdf"></div>
                             <div id="cdf4" class="cdf"></div>
                             <div id="cdf5" class="cdf"></div>
                             <div id="cdf6" class="cdf"></div>
                         </div>
                      </div>
                      <p class="btmNote" id="note1"><span style="font-weight:bold;background-color:#FFFF00;color:#000000;">NOTICE:</span> ALL VEHICLES OFFERED IN THIS PROMOTION ARE BRAND NEW VEHICLES AND INCLUDE GENERAL MOTORS' FULL FACTORY WARRANTY. 
                        FINANCING WILL ALSO BE MADE AVAILABLE TO ALL QUALIFYING CUSTOMERS. ALL FACTORY REBATES AND INCENTIVES AVAILABLE AT THE TIME OF PURCHASE 
                        WILL ALSO BE GIVEN TO CUSTOMERS THROUGH THIS EXLUSIVE PROMOTION!</p>
                      <p class="btmNote" id="note2">FINANCING RATES AS LOW AS 0.00% APR DEPENDING UPON INDIVIDUAL CREDIT WORTHINESS.<br>
                        REPRESENTATIVES FOR MULTIPLE LENDING INSTITUTIONS WILL COMPETE FOR YOUR AUTO LOAN.</p>
                      <p class="btmNote" id="note3">IN ADDITION, YOU MAY HAVE YOUR VEHICLE SERVICED AT ANY GM AUTHORIZED CERTIFIED SERVICING FACILITY LOCATED ANYWHERE WITHIN THE ENTIRE UNITED STATES. 
                        THIS INCLUDES VIRTUALLY ANY CHEVROLET, BUICK, CADILLAC, AND GMC AUTHORIZED FRANCHISE DEALER.</p>
                      <?php }?>
                  </div>  
                  <div id="vFeaturesDlg"></div>
                  <div id="postPage">
                      <input type="hidden" id="ppPermalink">
                      <p id="ppBtn"></p>
                      
                      <p style="padding:10px 18%;background:#95b9ff;font-weight:bold">
                        The original website also sent an email to the user, in addition to sending the user's 
                        supplied data from the form directly to the dealership's CRM software via ADFXML format.
                      </p>
                      <p style="text-decoration:underline">An email has been sent to the address you provided.</p>
                      <p>It contains a permanent link to your certificate, as well as more information regarding the terms and conditions of all the
                         manufacturer incentives and rebates for which you may be eligible.</p>
                      <?php
                      $uDlr = $_SESSION['prefDlr'];
                      $getDlrInfo = mysqli_query($knoxyConn, "SELECT address,city,state,zip,phone,logo,mapparts,website FROM dealers WHERE id=$uDlr") or die(mysqli_error($knoxyConn));
                      $gdi = mysqli_fetch_assoc($getDlrInfo);
                      $dealerPhone = $gdi['phone'];
                      $dealerLogo = "assets/img/dealers/" . $gdi['logo'];
                      $dealerAddress = $gdi['address'] . '<br>' . $gdi['city'] . ', ' . $gdi['state'] . ' ' . $gdi['zip'];
                      $dlrWebsite = $gdi['website'];
                      $dlrMapParts = json_decode($gdi['mapparts']);
                      $dmpVals = array();
                      foreach($dlrMapParts->geometry->location as $dmp) {
                          array_push($dmpVals, $dmp);
                      }
                      $dmpLat = $dmpVals[0];
                      $dmpLng = $dmpVals[1];
                      ?>
                      <p id="ppPhone">CALL US AT <?php echo $dealerPhone;?> FOR MORE INFORMATION!</p>
                      <div class="eight columns dlrInfo">
                          <a href="<?php echo $dlrWebsite;?>" target="_blank"><img src="<?php echo $dealerLogo;?>"></a><br>
                          <span id="dealerAddress"><?php echo $dealerAddress;?></span><br>
                          
                      </div>
                      <div class="eight columns dlrInfo" style="float:right">
                          <div id="ppMap">&nbsp;</div>
                          <input type="hidden" id="ppmLat" value="<?php echo $dmpLat;?>">
                          <input type="hidden" id="ppmLng" value="<?php echo $dmpLng;?>">
                      </div>
                      <div id="ppDirectionsWrap" class="eight columns dlrInfo">
                          
                          <input id="ppAddress" type="text" placeholder="Enter your address for directions">
                          <div id="ppAl2">
                            <input id="ppCity" type="text" placeholder="City"> &nbsp;
                            <input id="ppState" type="text" maxlength="2"> &nbsp;
                            <input id="ppZip" type="text" maxlength="5" value="<?php echo $_SESSION['userZip'];?>">
                          </div>
                          <div id="ppAl3">
                            <input type="button" id="ppDirectionsBtn" value="Route Directions">
                          </div>          
                      </div>
                  </div>
                     
                </header>          
             </div>
         </section>
        <p align="center"><a href="<?php echo $dlrPrivacyPolicy;?>" target="_blank">Privacy Policy</a><br>&copy; 2013-2015 Auto Dealer Results, LLC.</a></p>

    </div>
    <?php if(isset($_GET['test'])) {?>
    <script>
    $('#prePage').hide();
    $('#postPage').show();
    initDealerMap();
    </script>
    <?php }?>
</body>
</html>
