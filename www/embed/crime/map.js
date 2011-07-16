var pot, params, typeSelector;

$(function() {
try {
    var mm = com.modestmaps;

    params = parseQueryString(location.search);
    if (!params.base) params.base = "pale_dawn";
    // TODO: uncomment me?
    if (!params.baseURL) params.baseURL = baseURL;
    

    pot = new Dots.Potting(params);
    pot.setTitle();
    
    // map controls
    $(".zoom-in").click(function(e){
      e.preventDefault();
      pot.map.zoomIn(); 
    });
    $(".zoom-out").click(function(e){
      e.preventDefault();
      pot.map.zoomOut(); 
    });
    
    // adjust controls if title
    if (params.title) {
       $(".controls").css("top",($("#title").height()+20)+"px");
    }

    pot.dotsLayer = new mm.MarkerLayer(pot.map);

    if (params.ui == "1") {
        typeSelector = new CrimeTypeSelector("#crime_types_wrap","#crime_types", pot.dotsLayer);
        if (params.types) {
            typeSelector.defaultTypeSelected = false;
            typeSelector.selectTypes(params.types.split(","));
        }
    } else {
        $("#crime_types").remove();
    }

    var dotTemplate = $("#dot").template();
    pot.makeDot = function(feature) {
        var crime_type = getCrimeType(feature.properties),
            crime_group = getCrimeGroup(crime_type),
            data = {
                type: crime_type, 
                group: crime_group, 
                label: abbreviate(crime_type),
                desc: getCrimeDesc(feature.properties),
                props: feature.properties
            },
            marker = $.tmpl(dotTemplate, data);
            

        marker.data("feature", feature);
        marker.data("crime_type", crime_type);
        marker.data("crime_group", crime_group);

        if (typeSelector) {
            var label = typeSelector.addLabel(data);
            if (!label.data("selected")) {
                marker.css("display", "none");
            } else {
            }
        }
        return marker[0];
    };
    // need a callback on load to resize menu
    var req = pot.load(null, function(){
        if (typeSelector) {
            typeSelector.resize();
        }
    },null);
    
    ////////////////////////////
    // ARE WE IN CONFIG MODE ////////////
    // SHould we do this .. this way?? //
    /////////////////////////////////////
    var _inConfig = null;
    try{ _inConfig = window.parent.ds_config.hasher; }catch(e){}
    /////////////////////////////////////////
    // used to update coordinates in config only
    function showhash(){
        _inConfig(location.hash);
    }

    if((_inConfig) && (typeof _inConfig == 'function')){
        pot.map.addCallback("drawn", defer(showhash, 100));
    }
    /////////////////////////////////////////

} catch (e) {
    console.error("ERROR: ", e);
    pot.error("ERROR: " + e);
}
});

function CrimeTypeSelector(wrapper,selector, layer) {
    this.wrapper = $(wrapper);
    this.container = $(selector);
    this.layer = layer;
    this.labelsByType = {};
    this.selectedTypes = {};
}

CrimeTypeSelector.prototype = {
    container: null,
    layer: null,
    labels: null,
    labelsByType: null,
    selectedTypes: null,
    defaultTypeSelected: true,
    wrapper:null,

    getSortKey: function(data) {
        var indexes = {"violent": 1, "qol": 2, "property": 3};
        return [indexes[data.group] || 9, data.label || data.type].join(":");
    },

    addLabel: function(data) {
        var type = data.type;

        if (this.labelsByType[type]) {
            var label = this.labelsByType[type];
            label.data("count", label.data("count") + 1);
            return  label;
        }

        var label = $("<li/>")
            .data("type", type)
            .data("sort", this.getSortKey(data))
            .data("count", 1)
            .data("data", data)
            .addClass(data.group)
            .append($('<span class="group"/>')
                    .text(data.label))
            .append($('<span class="title"/>')
                .text(data.title || data.type));

        var that = this;
        label.click(function(e) {
            that.onLabelClick($(this), e);
            e.preventDefault();
        });

        this.container.append(label);
        this.labelsByType[type] = label;
        this.sortLabels();

        if (this.selectedTypes.hasOwnProperty(data.label)) {
            this.selectedTypes[type] = this.selectedTypes[data.label];
        }
        var selected = this.selectedTypes[type];
        if (typeof selected == "undefined") {
            selected = this.selectedTypes[type] = this.defaultTypeSelected;
        }
        if (selected) {
            label.data("selected", true);
            this.selectType(type);
        } else {
            label.addClass("off");
            this.unselectType(type);
        }
        return label;
    },

    onLabelClick: function(label, e) {
        var selected = !label.data("selected"),
            type = label.data("type");
        label.data("selected", selected);
        if (selected) {
            this.selectType(type);
        } else {
            this.unselectType(type);
        }
        label.toggleClass("off", !selected);
    },

    selectType: function(type) {
        var markers = this.layer.markers,
            len = markers.length;
        for (var i = 0; i < len; i++) {
            var marker = $(markers[i]);
            if (marker.data("crime_type") == type) {
                marker.css("display", "");
            }
        }
    },

    unselectType: function(type) {
        var markers = this.layer.markers,
            len = markers.length;
        for (var i = 0; i < len; i++) {
            var marker = $(markers[i]);
            if (marker.data("crime_type") == type) {
                marker.css("display", "none");
            }
        }
    },

    selectTypes: function(types) {
        if (types) {
            for (var i = 0; i < types.length; i++) {
                this.selectedTypes[types[i]] = true;
            }
            for (var type in this.labelsByType) {
                var label = this.labelsByType[type],
                    selected = this.selectedTypes[type];
                label.data("selected", selected)
                    .toggleClass("off", !selected);
            }
        }
        var markers = this.layer.markers,
            len = markers.length;
        for (var i = 0; i < len; i++) {
            var marker = $(markers[i]),
                type = marker.data("crime_type"),
                label = marker.data("data").label,
                selected = this.selectedTypes[type] || this.selectedTypes[label];
            marker.css("display", selected ? "" : "none");
        }
    },

    sortLabels: function() {
        var labels = {};
        var sortables = this.container.children().toArray().map(function(el) {
            var label = $(el),
                key = label.data("sort");
            labels[key] = label;
            /*console.log(label[0],key)*/
            return key;
        });
        sortables = sortables.sort(function(a, b) {
            return (b > a) ? -1 : (b < a) ? 1 : 0;
        });
        var len = sortables.length;
        for (var i = 0; i < len; i++) {
            labels[sortables[i]].appendTo(this.container);
        }
    }, 
    resize: function(){

        var _parent = this.container.parent();
        var _container_offset = this.container.offset();
        if(_container_offset.top + this.container.height() > _parent.height()){
            var _w = this.container.width();
            var _h = (_parent.height() - _container_offset.top) - 20;
            this.container.css("width",_w+25+"px").css("height",_h+"px").css("overflow","auto").css("padding-top","10px").css("padding-bottom","10px");
        }
    }
};

function getCrimeDesc(props) {
    return props["description"] || props["crime description"] || "?";
}

function getCrimeType(props) {
    return props["crime type"] || props["Crime Type"] || props["Crime type"] || "Unknown";
}

function getCrimeGroup(crime_type) {
    switch (crime_type.toUpperCase()) {
        case "AGGRAVATED ASSAULT":
        case "MURDER": case "HOMICIDE":
        case "ROBBERY":
        case "SIMPLE ASSAULT":
        case "BATTERY":
        case "SUICIDE":
        case "DOMESTIC VIOLENCE":
            return "violent";
        case "DISTURBING THE PEACE":
        case "NARCOTICS": case "DRUGS":
        case "ALCOHOL":
        case "PROSTITUTION":
        case "SOLICITING A PROSTITUTE":
        case "ATTEMPTED BATTERY":
        case "CIVIL SIDEWALKS/SIT-LIE":
        case "DRUNK DRIVING":
            return "qol";
        case "THEFT":
        case "VEHICLE THEFT":
        case "VANDALISM":
        case "BURGLARY":
        case "ARSON":
        case "AUTO THEFT":
        case "BICYCLE THEFT":
        case "MOTORCYCLE THEFT":
        case "GRAFFITI":
        case "BURGLARY HOME":
        case "BURGLARY - HOME":
        case "BURGLARY COMMERCIAL":
        case "BURGLARY - COMMERCIAL":
        case "BURGLARY VEHICLE":
        case "BURGLARY - VEHICLE":
        case "FRAUD":
            return "property";
    }
    return "unknown";
}



function getDateTime(props) {
    if (props.hasOwnProperty("date") && props.hasOwnProperty("time")) {
        return " on " + props["date"] + " @ " + props["time"];
    } else if (props.hasOwnProperty("date_time")) {
        return " on " + props["date_time"];
    } else if (props.hasOwnProperty("date")) {
        return " on " + props["date"];
    } else {
        return " on " + props["created"];
    }
}

function abbreviate(group) {
    var words = group.split(" ");
    //console.log(group, group.indexOf(" "), words.concat());
    if (words.length > 1) {
        var first = words.shift();
        while (abbreviate.stopWords.indexOf(words[0].toLowerCase()) > -1) {
            words.shift();
        }
        var second = words.shift();
        return (first.charAt(0) + second.charAt(0)).toUpperCase();
    } else {
        return group ? capitalizeWord(group.substr(0, 2)) : "?";
    }
} 
abbreviate.stopWords = ["of", "the", "for", "and", "with", "-"];
