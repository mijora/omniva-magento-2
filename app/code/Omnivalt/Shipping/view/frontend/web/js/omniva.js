var omniva_last_selected_terminal = '';
(function ( $ ) {
    $.fn.omniva = function(options) {
        var settings = $.extend({
            maxShow: 8,
            showMap: true,
            omnivadata: [],
        }, options );
        var omnivadata = settings.omnivadata;
        //console.log(omnivadata);
        var timeoutID = null;
        var currentLocationIcon = false;
        var autoSelectTerminal = false;
        var searchTimeout = null;
        var select = $(this);
        var select_terminal = omnivadata.text_select_terminal;
        var not_found = omnivadata.not_found;
        var terminalIcon = null;
        var homeIcon = null;
        var map = null;
        //console.log(settings.terminals);
        //var terminals = JSON.parse(settings.terminals);
        var terminals = [];
        $(this).find("option").each(function(){
            if ($(this).val()){
                var terminal = {
                    name: $(this).attr('data-name'),
                    zip: $(this).val(),
                    y: $(this).attr('data-x'),
                    x: $(this).attr('data-y'),
                    comment: $(this).attr('data-comment'),
                    address: $(this).attr('data-location'),
                    city: $(this).attr('data-city'),
                }
                terminals.push(terminal);
            }
        });
        //settings.terminals = $.map(settings.terminals, function(value, index) { return [value]; });
        /*
        terminals.sort(function(a, b) {
                if (a.name < b.name) {
                    return -1;
                }
                if (a.name > b.name) {
                    return 1;
                }
                    return 0;
            }); 
        */
        //console.log(terminals);
        var selected = false;
        var previous_list = [];
        select.hide();
        if (select.val()){
            selected = {'id':select.val(),'text':select.find('option:selected').text(),'distance':false};
        }
        /*
        select.find('option').each(function(i,val){
           if (val.value != "")
            terminals.push({'id':val.value,'text':val.text,'distance':false}); 
           if (val.selected == true){
               selected = {'id':val.value,'text':val.text,'distance':false};
           }
               
        });
        */
        var container = $(document.createElement('div'));
        container.addClass("omniva-terminals-list");
        var dropdown = $('<div class = "dropdown">'+omnivadata.text_select_terminal+'</div>');
        updateSelection();
        
        var search = $('<input type = "text" placeholder = "'+omnivadata.text_enter_address+'" class = "search-input"/>');
        var loader = $('<div class = "loader"></div>').hide();
        var list = $(document.createElement('ul'));
        var showMapBtn = $('<li><a href = "#" class = "show-in-map">'+omnivadata.text_show_in_map+'</a></li>');
        var showMore = $('<div class = "show-more"><a href = "#">'+omnivadata.text_show_more+'</a></div>').hide();
        var innerContainer = $('<div class = "inner-container"></div>').hide();
        
        $(container).insertAfter(select);
        $(innerContainer).append(search,loader,list,showMore);
        $(container).append(dropdown,innerContainer);
        
        if (settings.showMap == true){
            initMap();
        }
        
        refreshList(false);
        
        list.on('click','a.show-in-map',function(e){
            e.preventDefault();            
            showModal();
        });
        $('body').on('click','.show-omniva-map',function(e){
            e.preventDefault();            
            showModal();
        });
        
        showMore.on('click',function(e){
            e.preventDefault();
            showAll();
        });
        
        dropdown.on('click',function(){
            toggleDropdown();
        });
        
        select.on('change',function(){
            selected = {'id':$(this).val(),'text':$(this).find('option:selected').text(),'distance':false};
            updateSelection();
        });
        
    
        search.on('keyup',function(){
            clearTimeout(searchTimeout);      
            searchTimeout = setTimeout(function() { suggest(search.val())}, 400);    
                  
        });
        search.on('selectpostcode',function(){
            findPosition(search.val(),true);    
                  
        });
        
        search.on('keypress',function(event){
            if (event.which == '13') {
              event.preventDefault();
            }
        });
        
        $(document).on('mousedown',function(e){
            var container = $(".omniva-terminals-list");
            if (!container.is(e.target) && container.has(e.target).length === 0 && container.hasClass('open')) 
                toggleDropdown();
        });   
        
        $('.omniva-back-to-list').off('click').on('click',function(){
            listTerminals(terminals,0,previous_list);
            $(this).hide();
        });
        
        select.on('change',function(){
            omniva_last_selected_terminal = $(this).val();         
        });
       
        searchByAddress();
        
        
        function showModal(){
            getLocation();
            $('#omniva-search input').val(search.val());
            //$('#omniva-search button').trigger('click');
              if ($('.omniva-terminals-list input.search-input').val() != ''){
                  $('#omniva-search input').val($('.omniva-terminals-list input.search-input').val());
                 // $('#omniva-search button').trigger('click')
              }
            if (selected != false){
                //console.log('showmodal');
                $(terminals).each(function(i,val){
                    if (selected.id == val.zip){
                        zoomTo([val.x, val.y], selected.id);
                        return false;
                    }
                });
            }
            $('#omnivaLtModal').show();
            //getLocation();
            var event;
            if(typeof(Event) === 'function') {
                event = new Event('resize');
            }else{
                event = document.createEvent('Event');
                event.initEvent('resize', true, true);
            }
            window.dispatchEvent(event);
            //console.log('1');
          }

        function searchByAddress(){
            if (selected == false){
            
            if (omnivadata.postcode.length > 3){
                    search.val(omnivadata.postcode).trigger('selectpostcode');
                }
            }
        }

        function showAll(){
            list.find('li').show();
            showMore.hide();
        }
        
        function refreshList(autoselect){            
            $('.omniva-back-to-list').hide();
            var counter = 0;
            var city = false;
            var html = '';
            list.html('');
            $('.found_terminals').html('');
            $.each(terminals,function(i,val){
                var li = $(document.createElement("li"));
                li.attr('data-id',val.zip);
                li.html(val.name);
                if (val.distance !== undefined && val.distance != false){
                    li.append(' <strong>' + val.distance + 'km</strong>');  
                    counter++;
                    if (settings.showMap == true && counter <= settings.maxShow){
                        //console.log('add-to-map');
                        html += '<li data-pos="['+[val.x, val.y]+']" data-id="'+val.zip+'" ><div><a class="omniva-li">'+counter+'. <b>'+val.name+'</b></a> <b>'+val.distance+' km.</b>\
                                  <div align="left" id="omn-'+val.zip+'" class="omniva-details" style="display:none;"><small>\
                                  '+val.address+' <br/>'+val.comment+'</small><br/>\
                                  <button type="button" class="btn-marker" style="font-size:14px; padding:0px 5px;margin-bottom:10px; margin-top:5px;height:25px;" data-id="'+val.zip+'">'+select_terminal+'</button>\
                                  </div>\
                                  </div></li>';
                    }
                } else {
                    if (settings.showMap == true ){
                        //console.log('add-to-map');
                        html += '<li data-pos="['+[val.x, val.y]+']" data-id="'+val.zip+'" ><div><a class="omniva-li">'+(i+1)+'. <b>'+val.name+'</b></a>\
                                  <div align="left" id="omn-'+val.zip+'" class="omniva-details" style="display:none;"><small>\
                                  '+val.address+' <br/>'+val.comment+'</small><br/>\
                                  <button type="button" class="btn-marker" style="font-size:14px; padding:0px 5px;margin-bottom:10px; margin-top:5px;height:25px;" data-id="'+val.zip+'">'+select_terminal+'</button>\
                                  </div>\
                                  </div></li>';
                    }
                }
                if (selected != false && selected.id == val.zip){
                    li.addClass('selected');
                }
                if (counter > settings.maxShow){
                    li.hide();
                }
                if (val.city != city){
                    var li_city = $('<li class = "city">'+val.city+'</li>');
                    if (counter > settings.maxShow){
                        li_city.hide();
                    }
                    list.append(li_city);
                    city = val.city;
                }
                list.append(li);
            });
            list.find('li').on('click',function(){
                if (!$(this).hasClass('city')){
                    list.find('li').removeClass('selected');
                    $(this).addClass('selected');
                    selectOption($(this));
                }
            });
            if (autoselect == true){
                var first = list.find('li:not(.city):first');
                list.find('li').removeClass('selected');
                first.addClass('selected');
                selectOption(first);
            }
            var selectedLi = list.find('li.selected');
            var topOffset = 0;
            /*
            if (selectedLi !== undefined){
                topOffset = selectedLi.offset().top - list.offset().top + list.scrollTop();                
            }
            console.log(topOffset);
            */
            list.scrollTop(topOffset);
            if (settings.showMap == true){
                document.querySelector('.found_terminals').innerHTML = '<ul class="omniva-terminals-listing" start="1">'+html+'</ul>';
                if (selected != false && selected.id != 0){
                    map.eachLayer(function (layer) { 
                        if (layer.options.terminalId !== undefined && L.DomUtil.hasClass(layer._icon, "active")){
                            L.DomUtil.removeClass(layer._icon, "active");
                        }
                        if (layer.options.terminalId == selected.id) {
                            //layer.setLatLng([newLat,newLon])
                            L.DomUtil.addClass(layer._icon, "active");
                        } 
                    });
                }
            }
        }
        
        function selectOption(option){
            select.val(option.attr('data-id'));
            select.trigger('change');
            selected = {'id':option.attr('data-id'),'text':option.text(),'distance':false};
            updateSelection();
            closeDropdown();
        }
        
        function updateSelection(){
            if (selected != false){
               dropdown.html(selected.text); 
            }
        }
        
        function toggleDropdown(){
            if (container.hasClass('open')){
                innerContainer.hide();
                container.removeClass('open') 
            } else {
                innerContainer.show();
                container.addClass('open');
            }
        }  
        
        function closeDropdown(){
            if (container.hasClass('open')){
                innerContainer.hide();
                container.removeClass('open') 
            } 
        }
        
        function resetList(){
   
            $.each( terminals, function( key, location ) {
                location.distance = false;
                
            });
    
            terminals.sort(function(a, b) {
                var distOne = a[0];
                var distTwo = b[0];
                if (parseFloat(distOne) < parseFloat(distTwo)) {
                    return -1;
                }
                if (parseFloat(distOne) > parseFloat(distTwo)) {
                    return 1;
                }
                    return 0;
            });   
        }
        
        function calculateDistance(y,x){
   
            $.each( terminals, function( key, location ) {
                distance = calcCrow(y, x, location.x, location.y);
                location.distance = distance.toFixed(2);
                
            });
    
            terminals.sort(function(a, b) {
                var distOne = a.distance;
                var distTwo = b.distance;
                if (parseFloat(distOne) < parseFloat(distTwo)) {
                    return -1;
                }
                if (parseFloat(distOne) > parseFloat(distTwo)) {
                    return 1;
                }
                    return 0;
            });   
        }
        
        function toRad(Value) 
        {
           return Value * Math.PI / 180;
        }
    
        function calcCrow(lat1, lon1, lat2, lon2) 
        {
          var R = 6371;
          var dLat = toRad(lat2-lat1);
          var dLon = toRad(lon2-lon1);
          var lat1 = toRad(lat1);
          var lat2 = toRad(lat2);
    
          var a = Math.sin(dLat/2) * Math.sin(dLat/2) +
            Math.sin(dLon/2) * Math.sin(dLon/2) * Math.cos(lat1) * Math.cos(lat2); 
          var c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a)); 
          var d = R * c;
          return d;
        }
        
        function findPosition(address,autoselect){
            //console.log(address);
            if (address == "" || address.length < 3){
                resetList();
                showMore.hide();
                refreshList(autoselect);
                return false;
            }
            $.getJSON( "https://geocode.arcgis.com/arcgis/rest/services/World/GeocodeServer/findAddressCandidates?singleLine="+address+"&sourceCountry="+omnivadata.omniva_current_country+"&category=&outFields=Postal&maxLocations=1&forStorage=false&f=pjson", function( data ) {
              if (data.candidates != undefined && data.candidates.length > 0){
                calculateDistance(data.candidates[0].location.y,data.candidates[0].location.x);
                refreshList(autoselect);
                list.prepend(showMapBtn);
                //console.log('add');
                showMore.show();
                if (settings.showMap == true){
                    setCurrentLocation([data.candidates[0].location.y,data.candidates[0].location.x]);
                }
              }
            });
        }
        
        function suggest(address){
            $.getJSON( "https://geocode.arcgis.com/arcgis/rest/services/World/GeocodeServer/suggest?text="+address+"&f=pjson&sourceCountry="+omnivadata.omniva_current_country+"&maxSuggestions=1", function( data ) {
              if (data.suggestions != undefined && data.suggestions.length > 0){
                findPosition(data.suggestions[0].text,false);
              }
            });
        }
        
        function initMap(){
          $('#omnivaMapContainer').html('<div id="omnivaMap"></div>');
          var locations = terminals;
          let _coordsArray = [];
          locations.forEach(item => {
            _coordsArray.push([item.x, item.y]);
          });
          let bounds = new L.LatLngBounds(_coordsArray);
          map = L.map('omnivaMap').setView(bounds.getCenter(),7);

          L.tileLayer('https://maps.omnivasiunta.lt/tile/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.omniva.lt">Omniva</a>' +
                    ' | Map data &copy; <a href="https://www.openstreetmap.org/">OpenStreetMap</a> contributors, <a href="https://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>'
            }).addTo(map);

            var Icon = L.Icon.extend({
                options: {
                    //shadowUrl: 'leaf-shadow.png',
                    iconSize:     [29, 34],
                    //shadowSize:   [50, 64],
                    iconAnchor:   [15, 34],
                    //shadowAnchor: [4, 62],
                    popupAnchor:  [-3, -76]
                }
            });
          
          var Icon2 = L.Icon.extend({
                options: {
                    iconSize:     [32, 32],
                    iconAnchor:   [16, 32]
                }
            });

          var terminalIconFile = "sasi.png";
            if (omnivadata.omniva_current_country == "FI") {
                terminalIconFile = "sasi_mh.svg";
            }
            
          
            terminalIcon = new Icon({iconUrl: omnivadata.omniva_plugin_url + terminalIconFile});
            homeIcon = new Icon2({iconUrl: omnivadata.omniva_plugin_url+'locator_img.png'});
          
          
            jQuery.each( locations, function( key, location ) {
              L.marker([location.x, location.y], {icon: terminalIcon, terminalId:location.zip }).on('click',function(e){ listTerminals(locations,0,this.options.terminalId);terminalDetails(this.options.terminalId);}).addTo(map);
            });
          
          //show button
          $('.show-omniva-map').show(); 
          
          $('#terminalsModal').on('click',function(){$('#omnivaLtModal').hide();});
          $('#omniva-search input').off('keyup focus').on('keyup focus',function(){
                clearTimeout(timeoutID);      
                timeoutID = setTimeout(function(){ autoComplete($('#omniva-search input').val())}, 500);    
                      
            });
            
            $('.omniva-autocomplete ul').off('click').on('click','li',function(){
                $('#omniva-search input').val($(this).text());
                /*
                if ($(this).attr('data-location-y') !== undefined){
                    setCurrentLocation([$(this).attr('data-location-y'),$(this).attr('data-location-x')]);
                    calculateDistance($(this).attr('data-location-y'),$(this).attr('data-location-x'));
                    refreshList(false);
                }
                */
                $('#omniva-search #map-search-button').trigger('click');
                $('.omniva-autocomplete').hide();
            });
            $(document).click(function(e){
                var container = $(".omniva-autocomplete");
                if (!container.is(e.target) && container.has(e.target).length === 0) 
                    container.hide();
            });
          
            $('#terminalsModal').on('click',function(){
                $('#omnivaLtModal').hide();
            });
            $('#omniva-search #map-search-button').off('click').on('click',function(e){
              e.preventDefault();
              var postcode = $('#omniva-search input').val();
              findPosition(postcode,false);
            });
            $('.found_terminals').on('click','li',function(){
                zoomTo(JSON.parse($(this).attr('data-pos')),$(this).attr('data-id'));
            });
            $('.found_terminals').on('click','li button',function(){
                terminalSelected($(this).attr('data-id'));
            });
        }
        
        function autoComplete(address){
            var founded = [];
            $('.omniva-autocomplete ul').html('');
            $('.omniva-autocomplete').hide();
            if (address == "" || address.length < 3) return false;
            $('#omniva-search input').val(address);
            //$.getJSON( "https://geocode.arcgis.com/arcgis/rest/services/World/GeocodeServer/findAddressCandidates?singleLine="+address+"&sourceCountry="+omniva_current_country+"&category=&outFields=Postal,StAddr&maxLocations=5&forStorage=false&f=pjson", function( data ) {
            $.getJSON( "https://geocode.arcgis.com/arcgis/rest/services/World/GeocodeServer/suggest?text="+address+"&sourceCountry="+omnivadata.omniva_current_country+"&f=pjson&maxSuggestions=4", function( data ) {
              if (data.suggestions != undefined && data.suggestions.length > 0){
                  $.each(data.suggestions ,function(i,item){
                    //console.log(item);
                    //if (founded.indexOf(item.attributes.StAddr) == -1){
                        //const li = $("<li data-location-y = '"+item.location.y+"' data-location-x = '"+item.location.x+"'>"+item.address+"</li>");
                        const li = $("<li data-magickey = '"+item.magicKey+"' data-text = '"+item.text+"'>"+item.text+"</li>");
                        $(".omniva-autocomplete ul").append(li);
                    //}
                    //if (item.attributes.StAddr != ""){
                    //    founded.push(item.attributes.StAddr);
                    //}
                  });
              }
                  if ($(".omniva-autocomplete ul li").length == 0){
                      $(".omniva-autocomplete ul").append('<li>'+not_found+'</li>');
                  }
              $('.omniva-autocomplete').show();
            });
        }
        
        function terminalDetails(id) {
            /*
            terminals = document.querySelectorAll(".omniva-details")
            for(i=0; i <terminals.length; i++) {
                terminals[i].style.display = 'none';
            }
            */
            $('.omniva-terminals-listing li div.omniva-details').hide();
            id = 'omn-'+id;
            dispOmniva = document.getElementById(id)
            if(dispOmniva){
                dispOmniva.style.display = 'block';
            }      
        }
        
        function getLocation() {
          if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(function(loc) {
                if (selected == false){
                    setCurrentLocation([loc.coords.latitude, loc.coords.longitude]);
                }
            });
          } 
        }
        
        function setCurrentLocation(pos){
            if (currentLocationIcon){
              map.removeLayer(currentLocationIcon);
            }
            //console.log('home');
            currentLocationIcon = L.marker(pos, {icon: homeIcon}).addTo(map);
            map.setView(pos,16);
            //calculateDistance(pos[0],pos[1]);
            //refreshList(false);
        }
        function listTerminals(locations,limit,id){
              if (limit === undefined){
                  limit=0;
              }
              if (id === undefined){
                  id=0;
              }
             var html = '', counter=1;
             if (id != 0 && !$.isArray(id)){
                previous_list = [];
                $('.found_terminals li').each(function(){
                    previous_list.push($(this).attr('data-id'));
                });
                $('.omniva-back-to-list').show();
             }
             if ($.isArray(id)){
                previous_list = []; 
             }
            $('.found_terminals').html('');
            //console.log(id);
            $.each( locations, function( key, location ) {
              if (limit != 0 && limit < counter){
                return false;
              }
              if ($.isArray(id)){
                if ( $.inArray( location.zip, id) == -1){
                    return true;
                }
              }
              else if (id !=0 && id != location.zip){
                return true;
              }
              if (autoSelectTerminal && counter == 1){
                terminalSelected(location.zip,false);
              }
              var destination = [location.x, location.y]
              var distance = 0;
              if (location['distance'] != undefined){
                distance = location['distance'];
              }
              html += '<li data-pos="['+destination+']" data-id="'+location.zip+'" ><div><a class="omniva-li">'+counter+'. <b>'+location.name+'</b></a>';
              if (distance != 0) {
              html += ' <b>'+distance+' km.</b>';
              }
               html += '<div align="left" id="omn-'+location.zip+'" class="omniva-details" style="display:none;"><small>\
                                          '+location.address+' <br/>'+location.comment+'</small><br/>\
                                          <button type="button" class="btn-marker" style="font-size:14px; padding:0px 5px;margin-bottom:10px; margin-top:5px;height:25px;" data-id="'+location.zip+'">'+select_terminal+'</button>\
                                          </div>\
                                          </div></li>';
                                              
                              counter++;           
                               
            });
            document.querySelector('.found_terminals').innerHTML = '<ul class="omniva-terminals-listing" start="1">'+html+'</ul>';
            if (id != 0){
                map.eachLayer(function (layer) { 
                    if (layer.options.terminalId !== undefined && L.DomUtil.hasClass(layer._icon, "active")){
                        L.DomUtil.removeClass(layer._icon, "active");
                    }
                    if (layer.options.terminalId == id) {
                        //layer.setLatLng([newLat,newLon])
                        L.DomUtil.addClass(layer._icon, "active");
                    } 
                });
            }
        }
        
        function zoomTo(pos, id){
            terminalDetails(id);
            map.setView(pos,14);
            map.eachLayer(function (layer) { 
                if (layer.options.terminalId !== undefined && L.DomUtil.hasClass(layer._icon, "active")){
                    L.DomUtil.removeClass(layer._icon, "active");
                }
                if (layer.options.terminalId == id) {
                    //layer.setLatLng([newLat,newLon])
                    L.DomUtil.addClass(layer._icon, "active");
                } 
            });
        }
        
        function terminalSelected(terminal,close) {
          if (close === undefined){
              close = true;
          }
              var matches = document.querySelectorAll(".omnivaOption");
              for (var i = 0; i < matches.length; i++) {
                node = matches[i]
                if ( node.value.includes(terminal)) {
                  node.selected = 'selected';
                } else {
                  node.selected = false;
                }
              }
                    
              select.val(terminal);
              select.trigger("change");
              if (close){
                $('#omnivaLtModal').hide();
            }
        }
        
        return this;
    };
 
}( jQuery ));