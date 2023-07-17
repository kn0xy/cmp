<?php
session_start();
include('db.php');
//require 'mail.php';

$pageId = 'Chevy';

if(!isset($_GET['c'])) {
    // Retrieve form data
    $voi = stripslashes($_POST['voi']);
    $vInfo = explode('-', $voi);
    $vyr = $vInfo[0];
    $avoi = $vInfo[1];
    $color = $_POST['voiColor'];
    $fullColorName = $_POST['colorTitle'];
    $fname = addslashes(ucfirst($_POST['fname']));
    $lname = addslashes(ucfirst($_POST['lname']));
    $email = stripslashes($_POST['email']);
    $phone = stripslashes($_POST['phone']);
    $certZip = $_POST['certZip'];
    $trim = $_POST['voiTrim'];
    $dealer = $_POST['dealerId'];
    $ipaddr = $_SERVER['REMOTE_ADDR'];
    $permaLink = md5($fname.$lname.$email.$voi);
    
    // Verify the permalink does not already exist
    $tpl = md5($fname.$lname.$email.$voi);
    $vQuery = mysqli_query($knoxyConn,"SELECT fname, voi FROM certs WHERE permalink='$tpl'") or die(mysqli_error($knoxyConn));
    $eFname;
    $eVoi;
    $eYr;
    while($row = mysqli_fetch_array($vQuery)) {
        $eFname = $row['fname'];
        $voiData = explode('-', $row['voi']);
        $eVoi = $voiData[1];
        $eYr = $voiData[0];
    }
    if($eFname != '') {
        echo 'error::Sorry, '.$eFname.'! You can only be eligible for one promotion per vehicle.'."\n".'(Current certificate valid for '.$eYr.' '.$eVoi.')';
        exit();
    }
    
    // Determine Expiration date
    $today = date("Y-m-d");
    $tparts = explode("-", $today);
    $tit = strtotime($today);
    $texp = date("Y-m-d", strtotime("+1 month", $tit));
    $cincq = mysqli_query($knoxyConn, "SELECT title FROM incentives WHERE vehicle='$voi' AND trim='$trim'") or die(mysqli_error($knoxyConn));
    if(mysqli_num_rows($cincq) < 1) {
        $cincq = mysqli_query($knoxyConn, "SELECT title FROM incentives WHERE vehicle='$voi'") or die(mysqli_error($knoxyConn));
    }
    $searchTitle;
    while($sit = mysqli_fetch_assoc($cincq)) {
        $searchTitle = $sit['title'];
    }
    $earliest = strtotime($texp);
    $csxq = mysqli_query($knoxyConn, "SELECT stop FROM specials WHERE parent='$searchTitle'") or die(mysqli_error($knoxyConn));
    while($sStop = mysqli_fetch_assoc($csxq)) {
        $spcStop = strtotime($sStop['stop']);
        if($spcStop < $earliest) $earliest = $spcStop;
    }
    $exp = date("Y-m-d", $earliest);
    
    // Determine Certificate Number
    $ctm = 1;
    $cnq = mysqli_query($knoxyConn,"SELECT dateadded FROM certs");
    while($ddata = mysqli_fetch_array($cnq)) {
        $dparts = explode("-", $ddata['dateadded']);
        if($dparts[1] == $tparts[1]) {
            $ctm = $ctm + 1;
        }
    }
    $cn = 'CMP-'.$tparts[0].'-'.$tparts[1].'-'.$ctm.'-'.$dealer;
    
    // Format phone number
    $rp = preg_replace("/[^0-9]/","",$phone);
    $phone = substr($rp,0,3).'-'.substr($rp,3,3).'-'.substr($rp,6,4);
    
    // Determine max discount (dealer cash + trim incentives)
    $ddcq = mysqli_query($knoxyConn, "SELECT dealer_cash FROM cars WHERE model='$avoi' AND year='$vyr'") or die(mysqli_error($knoxyConn));
    $ddc = mysqli_fetch_assoc($ddcq);
    $ddCash = json_decode($ddc['dealer_cash']);
    $dealer_cash = 0;
    foreach($ddCash as $edc) {
        $edid = (int) $edc->dealer;
        $edlr = (int) $dealer;
        if($edid == $edlr) {
            $dealer_cash = (int) $edc->cash;
            break;
        }
    }
    $dtiq = mysqli_query($knoxyConn, "SELECT totalcash FROM incentives WHERE vehicle='$voi' AND trim='$trim'") or die(mysqli_error($knoxyConn));
    if(mysqli_num_rows($dtiq) > 0) {
        $dti = mysqli_fetch_assoc($dtiq);
        $trim_incentives = (int) $dti['totalcash'];
    } else {
        $datiq = mysqli_query($knoxyConn, "SELECT totalcash FROM incentives WHERE vehicle='$voi' AND trim='All-Trims'") or die(mysqli_error($knoxyConn));
        $dti = mysqli_fetch_assoc($datiq);
        $trim_incentives = (int) $dti['totalcash'];
    }  
    $daui = $dealer_cash + $trim_incentives;

    // Save a record in the database
    $saveQuery = "INSERT INTO certs (permalink, certno, dateadded, expdate, userip, voi, toi, color, fname, lname, email, phone, zip, dealer, daui, address, redon, redby)
        VALUES ('$permaLink', '$cn', '$today', '$exp', '$ipaddr', '$voi', '$trim', '$color', '$fname', '$lname', '$email', '$phone', $certZip, $dealer, $daui, '', '0000-00-00', '')";
    mysqli_query($knoxyConn,$saveQuery) or die(mysqli_error($knoxyConn));
    
    /*
    // Send email to the user
    $emailer = new certMail($email, $fname, $voi, $trim, $color, $permaLink, $dealer);
    if(!$emailer->Send()) {
        mysqli_query($knoxyConn, "DELETE FROM certs WHERE permalink='$permaLink'") or die(mysqli_error($knoxyConn));
        $emailError = $emailer->Error();
        echo "error::$emailError";
        exit();
    }
    
    // Send ADFXML to dealer
    $xmlBody = '<?xml version="1.0"?>'.PHP_EOL;
    $xmlBody .= '<?adf version="1.0"?>'.PHP_EOL;
    $xmlBody .= '<adf>'.PHP_EOL;
    $xmlBody .= '<prospect>'.PHP_EOL;
    $xmlBody .= '<requestdate>NA</requestdate>'.PHP_EOL;
    $xmlBody .= '<vehicle>'.PHP_EOL;
    $xmlBody .= '<year>'.$vyr.'</year>'.PHP_EOL;
    $xmlBody .= '<make>Chevrolet</make>'.PHP_EOL;
    $xmlBody .= '<model>'.$avoi.'</model>'.PHP_EOL;
    $xmlBody .= '<trim>'.$trim.'</trim>'.PHP_EOL;
    $xmlBody .= '<colorcombination>'.PHP_EOL;
    $xmlBody .= '<interiorcolor>default</interiorcolor>'.PHP_EOL;
    $xmlBody .= '<exteriorcolor>'.$fullColorName.'</exteriorcolor>'.PHP_EOL;
    $xmlBody .= '<preference>1</preference>'.PHP_EOL;
    $xmlBody .= '</colorcombination>'.PHP_EOL;
    $xmlBody .= '</vehicle>'.PHP_EOL;
    $xmlBody .= '<customer>'.PHP_EOL;
    $xmlBody .= '<contact>'.PHP_EOL;
    $xmlBody .= '<name part="first">'.$fname.'</name>'.PHP_EOL;
    $xmlBody .= '<name part="last">'.$lname.'</name>'.PHP_EOL;
    $xmlBody .= '<email>'.$email.'</email>'.PHP_EOL;
    $xmlBody .= '<phone preferredcontact="1" type="voice" />'.PHP_EOL;
    $xmlBody .= '<phone>'.$phone.'</phone>'.PHP_EOL;
    $xmlBody .= '<address>'.PHP_EOL;
    $xmlBody .= '<street line="1">NA</street>'.PHP_EOL;
    $xmlBody .= '<city>NA</city>'.PHP_EOL;
    $xmlBody .= '<regioncode>NA</regioncode>'.PHP_EOL;
    $xmlBody .= '<postalcode>NA</postalcode>'.PHP_EOL;
    $xmlBody .= '<country>US</country>'.PHP_EOL;
    $xmlBody .= '</address> '.PHP_EOL;
    $xmlBody .= '</contact>'.PHP_EOL;
    $xmlBody .= '<comments>Chevy Marketing Promo</comments>'.PHP_EOL;
    $xmlBody .= '</customer>'.PHP_EOL;
    $xmlBody .= '<vendor><contact><name>Blossom Chevrolet</name></contact></vendor>'.PHP_EOL;
    $xmlBody .= '<provider>'.PHP_EOL;
    $xmlBody .= '<id source="ChevyMarketingPromo.US">NA</id>'.PHP_EOL;
    $xmlBody .= '<name>ChevyMarketingPromo.US</name>'.PHP_EOL;
    $xmlBody .= '<service>ChevyMarketingPromo.US</service>'.PHP_EOL;
    $xmlBody .= '<contact><name part="full">ChevyMarketingPromo.US</name></contact>'.PHP_EOL;
    $xmlBody .= '</provider>'.PHP_EOL;
    $xmlBody .= '</prospect>'.PHP_EOL;
    $xmlBody .= '</adf>'.PHP_EOL;
    
    if($emailer->LeadToDealer($xmlBody)) {
        // ADF sent successfully;
        // Create informational copy of the lead
        $imsg = '<p>Name: ' . $fname.' '.$lname .'</p>'. PHP_EOL;
        $imsg .= '<p>E-Mail: ' . $email .'</p>'. PHP_EOL;
        $imsg .= '<p>Phone: ' . $phone .'</p>'. PHP_EOL;
        $imsg .= '<p>Vehicle: ' . $vyr.' '.$avoi.' '.$trim.'</p>'. PHP_EOL;
        $imsg .= '<p>Color: '.$fullColorName.'</p>'.PHP_EOL;
        $emailer->InfoCopy($imsg);
        echo $permaLink;
    } else {
        echo "error::An unexpected error occurred. Please try again later.";
    }
     * 
     */
    echo $permaLink;
    exit();
} else {
    $certExists = true;
    // Generate certificate
    $voi;
    $voiyr;
    $toi;
    $certno;
    $expdate;
    $fname;
    $lname;
    $qrcode;
    $dealer;
    $daui;
    $pl = $_GET['c'];
    if($pl != 'preview') {
      $certQuery = mysqli_query($knoxyConn,"SELECT voi,toi,certno,expdate,fname,lname,dealer,daui FROM certs WHERE permalink='$pl'") or die(mysqli_error($knoxyConn));
      if(mysqli_num_rows($certQuery) < 1) $certExists = false;
      while($row = mysqli_fetch_array($certQuery)) {
        $vd = explode('-', $row['voi']);
        $voi = $vd[1];
        $voiyr = $vd[0];
        $toi = $row['toi'];
        $fname = $row['fname'];
        $lname = $row['lname'];
        $certno = $row['certno'];
        $expdate = $row['expdate'];
        $dealer = $row['dealer'];
        $daui = $row['daui'];
      }
    } else {
        // TESTING
        $fname = "CMP";
        $lname = "Demo";
        $expdate = "2014-12-31";
        $certno = "Development-Testing";
        $vd = explode('-', $_GET['voi']);
        $voi = $vd[1];
        $voiyr = $vd[0];
        $toi = "";
        $dealer = $_GET['d'];
        $dauiQuery = mysqli_query($knoxyConn,"SELECT dealer_cash FROM cars WHERE model='$voi' AND year='$voiyr'") or die(mysqli_error($knoxyConn));
        $dauiObj = mysqli_fetch_assoc($dauiQuery);
        $daui = 0;
        $dca = json_decode($dauiObj['dealer_cash']);
        foreach($dca as $dco) {
            if($dco->dealer == $dealer) {
                $daui = $dco->daui;
                break;
            }
        }
    }
    // Determine certificate properties
    $certPropsQuery = mysqli_query($knoxyConn, "SELECT certimg, qrcode FROM cars WHERE model='$voi' AND year='$voiyr'") or die(mysqli_error($knoxyConn));
    $certProps = mysqli_fetch_assoc($certPropsQuery);
    $certBg = 'assets/img/certs/' . $certProps['certimg'];
    $certQr = 'assets/img/QR_Codes/' . $certProps['qrcode'];
    $award = '$'.number_format($daui).' off<span class="dPrice"><sup>✝</sup></span>&nbsp; the purchase of a new<br>'.$voiyr.' '.$voi.' '.$toi.'</span>';
    if($certExists) {
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Your Certificate From Chevy Marketing Promo</title>
  <meta name="author" content="Knoxy" >
  <meta name="viewport" content="width=device-width">
  <meta name="format-detection" content="telephone=no">
  <link rel="stylesheet" type="text/css" href="assets/css/cert.css" />
</head>
<body>
    <?php // Get dealership information
    $dealerInfoQuery = mysqli_query($knoxyConn, "SELECT * FROM dealers WHERE id=$dealer") or die(mysqli_error($knoxyConn));
    $dealerInfo = mysqli_fetch_assoc($dealerInfoQuery);
    ?>
    <div class="Certificate"><img src="<?php echo $certBg;?>" /></div>
    <div class="dealerLogo"><img src="assets/img/dealers/<?php echo $dealerInfo['logo'];?>"></div>
    <div class="cmpText1"><p>www.ChevyMarketingPromo.US</p></div>
    <div class="cmpText2">Non-Transferable Exclusive Offer</div>
    <div class="certNum"><p>Certificate No. <?php echo $certno;?></p></div>
    <div class="certExp"><p>Expiration Date: <?php echo date("F j, Y", strtotime($expdate));?></p></div>
    <div class="CertText"><p>This is to certify that</p></div>
    <div class="CertName"><h1><?php echo $fname . ' ' . $lname; ?></h1></div>
    <div class="CertText2"><p>is hereby entitled to</p></div>
    <div class="CertAward"><h1><?php echo $award; ?></h1></div>
    <div class="CertCode"><?php echo '<img src="'.$certQr.'" />'; ?></div>
    <div class="dealerInfo">
        <p>
            <span>CALL TOLL-FREE</span> 
            <span style="color:rgb(0,92,161);"><?php echo $dealerInfo['phone'];?></span> 
            <span>  
            <?php if($dealerInfo['csr1']) echo 'and ask for '.$dealerInfo['csr1'].' ';
            if($dealerInfo['csr2']) echo 'or '.$dealerInfo['csr2'].' ';?> to arrange for an appointment.
            </span>
        </p>
        <p class="otherText"><sup>✝</sup> &nbsp;&nbsp; Based on maximum eligibility for manufacturer incentives &amp; bonus offers in your area.
            Your personal discount may vary.<br>All available manufacturer rebates and incentives will be available to you at the time of purchase.</p>
    </div>
    <div class="AuthText">Authorized Program Dealer:</div>
    <div class="AuthDealer"><?php echo $dealerInfo['company'];?></div>
    <div class="dealerAddr"><?php echo $dealerInfo['address'];?> &nbsp; <?php echo $dealerInfo['city'];?>, <?php echo $dealerInfo['state'];?> <?php echo $dealerInfo['zip'];?></div>
    <div class="Disclaimer1">Limit one certificate per person, per vehicle.</div>
    <div class="Disclaimer2">Not valid with any other offers or discounts.</div>
    <div class="Copyright">&copy; 2013-2015 Auto Dealer Results, LLC.</div>
</body>
</html>
<?php } else {?>
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
</head>
<body>
    <header id="header-global" role="banner" style="background: #FFF;"> 
       <div class="container">
          <div class="row">
            <div id="logo"><img src="assets/img/cmp_header_02.jpg"></div>
          </div>
        </div>
    </header>
    <div id="main" style="margin-top:60px">
        <section id="form-content">
            <div class="container" id="contentWrapper" style="border:1px solid #333;border-radius:15px">
                <header class="section-heading row">
                    <p style="width:100%"><strong>Uh oh...</strong><br>
                        You requested an invalid certificate!<br>
                        If you are seeing this message in error, please contact ADR technical support.</p>
                </header>
            </div>
        </section>
    </div>
</body>
</html>
<?php } }?>
