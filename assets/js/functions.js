/* functions.js
 * This file contains all the JavaScript functions for site functionality.
 */

var isMobile = false;
var dealerMap;

function bannerLoad() {
    var screenWidth = parseInt(window.innerWidth);
    if(screenWidth < 768) isMobile = true;
    appendSlides();
    $('.banner').flexslider({
        slideshowSpeed: 3000,
        controlNav: false,
        directionNav: false
    });    
    $(document).tooltip();
    
    if(!isMobile) $('#phone').mask("999-999-9999");
    initHandlers();
}

function appendSlides() {
    $('.slides').append('<li><img class="imgSlide" src="assets/img/jdp.jpg"></li>')
        .append('<li><img class="imgSlide" src="assets/img/tss.jpg"></li>')
        .append('<li><img class="imgSlide" src="assets/img/tahoe.jpg"></li>')
        .append('<li><img class="imgSlide" src="assets/img/sva.jpg"></li>')
        .append('<li><img class="imgSlide" src="assets/img/impala.jpg"></li>')
        .append('<li><img class="imgSlide" src="assets/img/silverado.jpg"></li>');
}

function initHandlers() {
    $(window).on('resize', function() {
        var nw = window.innerWidth;
        if(nw < 768) {
            isMobile = true;
        } else {
            isMobile = false;
        }
        updateLayout();
    });
    
    $('body').on('click', '#helpBtn', function() {
        trimDetails();
    });
    
    // Form validation
    $.validator.addMethod('customphone', function (value, element) {
        return this.optional(element) || /^\d{3}-\d{3}-\d{4}$/.test(value) || /^\d{10}$/.test(value);
    }, "Please enter a valid phone number. (###-###-####)");
    $.validator.setDefaults({
            submitHandler: function() { generateCertificate(); }
    });
    $().ready(function() {
        $("#certData").validate({
            rules: {
                phone: 'customphone'
            },
            messages: {
                voi: "You must select a vehicle."
            }
        });
    });
    
    // Post-page Address Field
    $('body').on('keyup', '#ppAddress', function() {
        var eVal = $(this).val();
        var aParts = eVal.split(' ');
        if(aParts.length < 3 || eVal.length < 7) {
            if($('#ppAl2').is(':visible')) $('#ppAl2').slideToggle();
            if($('#ppAl3').is(':visible')) $('#ppAl3').slideToggle();
        } else {
            if(aParts[2].length > 1) {
                if(!$('#ppAl2').is(':visible')) $('#ppAl2').slideToggle();
                $('#ppAddress').animate({margin: '5px auto'});
                validateDirections();  
            } else {
                if($('#ppAl2').is(':visible')) $('#ppAl2').slideToggle();
                if($('#ppAl3').is(':visible')) $('#ppAl3').slideToggle();
                $('#ppAddress').animate({margin:'20px auto'});
            }
        }
    });
    
    // Post-page Address Line 2 Fields
    $('body').on('keyup', '#ppAl2 input:text', function() {
        validateDirections();
    });
    
    // Post-page Route Directions button
    $('body').on('click', '#ppDirectionsBtn', function() {
        routeDirections();
    });
}

function updateLayout() {
    if(isMobile) {
        $('#carDetails').insertAfter('#trimRow');
    } else {
        $('#carDetails').insertBefore('#note1');
    }
}

function carDetails(whichCar) {
    if(!$('#carImage').is(':visible')) {
        fetchDetails();
        if(!isMobile) {
          $('#welcomeText').fadeOut(250, function() {
            $('#carDetails').show();
            $('#carImage').fadeIn(250, function() {
              $('#cdWrap').fadeIn(250);
            });
          });
        } else {
            setTimeout(function() {
                var fnr = $('#fname').parent().parent();
                $(fnr).animate({marginTop: '350px'}, function() {
                    $('#welcomeText').slideToggle(250);
                    $('#carDetails').insertBefore(fnr).slideToggle(250, function() {
                        $(fnr).css('margin-top', 0);
                        $('#carImage').fadeIn(function() {
                            $('html, body').animate({
                                    scrollTop: $('#voiRow').offset().top
                                });
                            $('#cdWrap').fadeIn();
                        });
                    });
                        
                });
                
            }, 750);
        }
    } else {
        $('#carDetails').fadeOut(250, function() {
            fetchDetails();
            $('#carDetails').fadeIn(250);
        });
    }
    function fetchDetails() {
      $.ajax({
        type: "GET",
        url: "?vehicleInfo="+whichCar,
        success: function(result) {
            var response = JSON.parse(result);
            var theCar = response[0];
            var colors = response[1];
            var nTrims = parseInt(response[2].length);
            var imgName = theCar['defaultcolor'] + '.jpg';
            var vImage = 'url(assets/img/vehicles/'+colors[0][1]+'/'+imgName+')';
            // set vehicle color
            var defaultColor = theCar['defaultcolor'];
            var defaultTitle;
            $(colors).each(function() {
                if(this.colorname === defaultColor) defaultTitle = this.colortitle;
            });
            $('#carImage').css('background-image', vImage);  
            // set internal data
            $('#voiColor').val(defaultColor);
            $('#colorTitle').val(defaultTitle);
            $('#dealerCash').val(theCar['dcash']);
            // update color swatches
            $('#colorBar').html('');
            $(colors).each(function() {
                var thumbName = this[2] + '.jpg';
                var thumb = 'background-image:url(assets/img/vehicles/'+thumbName+');';
                var newDiv = document.createElement('div');
                newDiv.setAttribute('id', this[2]);
                newDiv.setAttribute('class', 'color');
                newDiv.setAttribute('style', thumb);
                newDiv.setAttribute('title', this[3]);
                newDiv.setAttribute('onclick', 'pickColor(this);');
                $('#colorBar').append(newDiv);
            });
            if(isMobile) {
              var swatches = $('#colorBar').children('.color').length;
              if(swatches > 8) {
                $('#colorBar').css('height', '76px').css('bottom','-38px');
              } else {
                $('#colorBar').css('height', '38px').css('bottom',0);
              }
            }
            // set the icons and stock info
            var cdf1bg = 'url(assets/img/vehicles/icons/'+theCar['cdf1icon']+'.png)';
            $('#cdf1').css('background-image', cdf1bg);
            $('#cdf1').html(theCar['cdf1content']);
            var cdf2bg = 'url(assets/img/vehicles/icons/'+theCar['cdf2icon']+'.png)';
            $('#cdf2').css('background-image', cdf2bg);
            $('#cdf2').html(theCar['cdf2content']);
            var cdf3bg = 'url(assets/img/vehicles/icons/'+theCar['cdf3icon']+'.png)';
            $('#cdf3').css('background-image', cdf3bg);
            $('#cdf3').html(theCar['cdf3content']);
            var cdf4bg = 'url(assets/img/vehicles/icons/'+theCar['cdf4icon']+'.png)';
            $('#cdf4').css('background-image', cdf4bg);
            $('#cdf4').html(theCar['cdf4content']);
            var cdf5bg = 'url(assets/img/vehicles/icons/'+theCar['cdf5icon']+'.png)';
            $('#cdf5').css('background-image', cdf5bg);
            if(nTrims > 1) {
                var cdf = '<span class="title">Choose one of</span><h2>'+nTrims+'</h2><p class="overall" style="margin-top:0;padding-top:0">trim styles</span>';
                var url = theCar['cdf5content'].split('"')[1];
                cdf += '<p style="font-size:0.95em"><a href="'+url+'" target="_blank">More Information</a></p>';
                $('#cdf5').css('background-position', 'left');
                $('#cdf5').html(cdf);
                showTrimSelector(true, response[2]);
            } else {
                $('#cdf5').css('background-position', 'left top');
                $('#cdf5').html(theCar['cdf5content']);
                showTrimSelector(false);
            }
            $('#cdf6').html(theCar['cdf6content']);
            var formattedDaui = '$';
            var dauiStr = theCar['daui'].toString();
            if(dauiStr.length > 4) {
                formattedDaui += dauiStr.substring(0, 2)+','+dauiStr.substring(2);
            } else if(dauiStr.length > 3) {
                formattedDaui += dauiStr.substring(0, 1)+','+dauiStr.substring(1);
            } else {
                formattedDaui += dauiStr;
            }
            $('#vDaui').html(formattedDaui);
            // preload the other colors
            setTimeout(function() {
                preloadImages(colors);
            }, 500);
        }
      });
      if(isMobile) {
          $('#voi').children('option').each(function() {
              if($(this).val() === whichCar) {
                  var thisCar = $(this).text();
                  if($('#mVoi').length === 0) {
                      var mVoi = document.createElement('span');
                      $(mVoi).prop('id','mVoi').css('font-style','italic').css('font-size','0.7em').html(thisCar).insertAfter('#voi');
                  } else {
                      $('#mVoi').html(thisCar);
                  }
              }
          });
      }
    }
}

function pickColor(colorLink) {
    var whichColor = colorLink.id;
    var vf = $('#voi').val();
    var vImg = 'url(assets/img/vehicles/'+vf+'/'+whichColor+'.jpg)';
    $('#carImage').css('background-image', vImg);
    $('#voiColor').val(whichColor);
    var jid = $(colorLink).attr('aria-describedby');
    var cTitle = $('#'+jid).children('.ui-tooltip-content').prop('innerHTML');
    $('#colorTitle').val(cTitle);
}

function trimDetails() {
    var dWidth = $('#prePage').width();
    if(dWidth > 600) dWidth = 600;
    var dTitle = $('#voi').val().replace('-',' ') + ' Trim Options';
    $('#vFeaturesDlg').dialog({
        modal: true,
        width: dWidth,
        title: dTitle,
        resizable: false,
        closeText: '',
        open: function() {
            $(document).tooltip("destroy").tooltip();
            $('#vFeaturesDlg').html('<p class="ajaxLoading">Loading trim details...</p>');
            loadFeatures();
        },
        close: function() {
            $(this).empty();
        },
        buttons: [
            {
              text: "OK",
              click: function() {
                  $(this).dialog("close");
              }
            }
        ]
    });
    
    function loadFeatures() {
        var vFeatures = new Array();
        $.ajax({
            type: "GET",
            url: "?vehicleFeatures="+$('#voi').val(),
            success: function(fResult) {
                vFeatures = jQuery.parseJSON(fResult);
                // generate tabs
                var tabsHtml = '<div id="tabs"><ul>';
                var menuHtml = '';
                var panelHtml = '';
                for(var f=0;f<vFeatures.length;f++) {
                    var pub = vFeatures[f]['pub'];
                    var trim = vFeatures[f]['trim'];
                    var ftrs = vFeatures[f]['features'];
                    var dsct = vFeatures[f]['discount'];
                    var tfmHtml = '<li><a href="#vtm-'+trim+'">'+pub+'</a></li>';
                    var tfpHtml = '<div id="vtm-'+trim+'">'+ftrs+'<input type="hidden" value="'+dsct+'"></div>';
                    menuHtml += tfmHtml;
                    panelHtml += tfpHtml;
                }
                tabsHtml += menuHtml + '</ul>';
                tabsHtml += panelHtml + '</div>';
                $('#vFeaturesDlg').html(tabsHtml);
                $('#tabs').tabs({
                    show: 250,
                    hide: 250
                });
            }
        });
    }
}

function generateCertificate() {
  $('#genBtn').val('Loading Your Certificate...');
  $('#genBtn').prop('disabled', true);
  var formData = $('#certData').serialize();
  $.ajax({
    type: "POST",
    url: "cert.php",
    data: formData,
    success: function(result) {
        if(result.indexOf("error::") !== -1) {
            $('#genBtn').val('Generate Certificate');
            $('#genBtn').removeProp('disabled');
            var err = result.split("::");
            alert(err[1]);
        } else {
            // Update UI
            var vbtn = document.createElement('button');
            $(vbtn).attr('type','button').css('width','100%').html('View Certificate').on('click', function() {
                window.open('cert.php?c='+result);
            });
            $(vbtn).button().css('font-size','1.4em');
            $('#prePage').slideToggle(1000, function() {
                $('#ppBtn').html(vbtn);
                $('#postPage').slideToggle(function() {
                    initDealerMap();
                });
            });
            $('#ppPermalink').val(result);
        }
    }
  });
}

function showTrimSelector(yes, trims) {
    if(yes) {
      if($('#trimRow').length === 0) {
        var trimRow = document.createElement('div');
        var L = document.createElement('div');
        var R = document.createElement('div');
        var voiTrim = document.createElement('select');
        var btnTitle = ((isMobile) ? 'Tap again for more information!' : 'More Information');
        $(voiTrim).prop('id','voiTrim').prop('name','voiTrim').prop('required',true);
        $(R).addClass('right').append(voiTrim).append('<img id="helpBtn" title="'+btnTitle+'" src="assets/img/help.png">');
        $(L).addClass('left').html('<span class="mh">&nbsp;&nbsp; &rarr; &nbsp;</span> <span style="font-size:0.9em">Desired Trim:</span>');
        $(trimRow).prop('id','trimRow').addClass('formRow').append(L).append(R).insertAfter('#voiRow');
        $('#voiRow').animate({height:'31px'});
        $(trimRow).slideToggle();
      }
      $('#voiTrim').html('<option value="NULL" selected disabled>Pick Trim</option>');
      for(var t=0;t<trims.length;t++) {
          $('#voiTrim').append('<option value="'+trims[t]+'">'+trims[t]+'</option>');
      }
    } else {
      if($('#trimRow').is(':visible')) {
        $('#voiRow').animate({height:'61px'});
        $('#trimRow').slideToggle(function() {
            $('#trimRow').remove();
        });
      }
    }
}

function changeLoc() {
    $.ajax({
        type: "GET",
        url: "index.php?changeLoc",
        success: function(result) {
            if(result==='success') {
                location.reload(false);
            }
        }
    });
}

function initDealerMap() {
    dealerMap = new GMaps({
        div: '#ppMap',
        lat: $('#ppmLat').val(),
        lng: $('#ppmLng').val(),
        width: 300,
        height: 200,
        zoom: 10,
        zoomControl: false,
        mapTypeControl: false,
        panControl: false,
        scaleControl: false,
        streetViewControl: false
    });
    dealerMap.addMarker({
        lat: $('#ppmLat').val(),
        lng: $('#ppmLng').val(),
        click: function() {
            dealerMap.setZoom(15);
            dealerMap.setCenter($('#ppmLat').val(), $('#ppmLng').val());
        }
    });
    // Prefill the "state" field for the map directions
    var stStr = $('#dealerAddress').html().split('<br>')[1];
    var sti = stStr.indexOf(', ') + 2;
    var state = stStr.substr(sti,2);
    $('#ppState').val(state);
}

function validateDirections() {
    var cityVal = $('#ppCity').val();
    var stateVal = $('#ppState').val();
    var zipVal = $('#ppZip').val();
    if(cityVal.length > 2 && stateVal.length===2 && zipVal.length===5 && parseInt(zipVal)) {
        if(!$('#ppAl3').is(':visible')) $('#ppAl3').slideToggle();
    } else {
        if($('#ppAl3').is(':visible')) $('#ppAl3').slideToggle();
    }
}

function routeDirections() {
    var addrVal = $('#ppAddress').val();
    var cityVal = $('#ppCity').val();
    var stateVal = $('#ppState').val();
    var zipVal = $('#ppZip').val();
    var geoSrc = addrVal+' '+cityVal+', '+stateVal+' '+zipVal;
    $('#ppDirectionsBtn').prop('disabled',true).val('Finding route...');
    // Geocode the source address
    GMaps.geocode({
        address: geoSrc,
        callback: function(results, status) {
            if(status==="OK") {
                // Add a green marker to the map
                var sll = results[0].geometry.location;
                dealerMap.addMarker({
                    lat: sll.lat(),
                    lng: sll.lng(),
                    icon: 'assets/img/green-marker.png',
                    animation: google.maps.Animation.DROP,
                    click: function() {
                        dealerMap.setZoom(15);
                        dealerMap.setCenter(sll.lat(), sll.lng());
                    }
                });
                dealerMap.fitZoom();
                var rsl = {
                    lat: sll.lat(),
                    lng: sll.lng()
                };
                findRoute(rsl);
            }
        }
    });
    function findRoute(startLoc) {
        var usrAddress = $('#ppAddress').val();
        var destLat = parseFloat($('#ppmLat').val());
        var destLng = parseFloat($('#ppmLng').val());
        var routeDest = [destLat, destLng];
        var routeOrigin = [startLoc.lat, startLoc.lng];
        var routed = false;
        // Draw the route on the map
        dealerMap.drawRoute({
            origin: routeOrigin,
            destination: routeDest,
            travelMode: 'driving',
            strokeColor: '#131540',
            strokeOpacity: 0.6,
            strokeWeight: 6,
            unitSystem: 'imperial'
        });
        // Update UI
        $('#ppDirectionsWrap').slideToggle(function() {
            var txtWrap = document.createElement('div');
            $(txtWrap).prop('id','ppDirectionsTxt').insertAfter('#ppDirectionsWrap');
            $(this).html('<p style="font-style:italic;width:100%;margin-top:25px">See below for directions:</p>').fadeIn();
        });
        // Print the text directions below the map
        function printInstructions(routeSteps) {
            $('#ppDirectionsTxt').empty();
            for(var s=0;s<routeSteps.length;s++) {
                var instructions = routeSteps[s].instructions.replace('div','span');
                var step = '<p><span class="ppStepDistance">'+routeSteps[s].distance.text+'</span>'+instructions+'.</p>';
                step = step.replace('<span s','<br><span s');
                $('#ppDirectionsTxt').append(step);
            }
            routed = true;
        }
        var waitForRoute = setInterval(function() {
            if(dealerMap.routes.length > 0) {
                printInstructions(dealerMap.routes[0].legs[0].steps);
                if($('#ppDirectionsTxt').children('p').length > 0) clearInterval(waitForRoute);
            }
        }, 100);
        // Save address after route has loaded
        var saveAddress = setInterval(function() {
            if(!routed) {
                return false;
            } else {
                clearInterval(saveAddress);
                appendAddress(usrAddress);
            } 
        }, 250);
    }
}
function appendAddress(usrAddr) {
    var usrCert = $('#ppPermalink').val();
    $.ajax({
        type: "POST",
        data: {
            address: usrAddr,
            pl: usrCert
        },
        url: "?certAppend",
        success: function(result) {
            if(result !== 'success') console.log(result);
        }
    });
}

function preloadImages(colorList) {
    var current = $('#carImage').css('background-image');
    var cbg = current.substring(current.lastIndexOf('/')+1, current.indexOf('.jpg'));
    $(colorList).each(function() {
        if(this.colorname !== cbg) {
            var color = this.colorname;
            var v = this.vehicle;
            var img = new Image();
            img.src = 'assets/img/vehicles/'+v+'/'+color+'.jpg';
        }
    });
}