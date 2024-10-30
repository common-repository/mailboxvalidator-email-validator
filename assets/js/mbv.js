jQuery(document).ready(function($) {
	// $( "#blacklist_domain" ).click(function() {
	// $("#blacklist_domain").on("focus",function(e) {
	// $("form").on("submit",function(e) {
		// e.preventDefault();
		// var $this = $(this);
		// var formData = $this.serialize();
		// console.log (formData);
		// alert (formData);
		// console.log('Hello');
		// var blacklist_domain_list = "";
		// var lines = $('#blacklist_domain').val().split('\n');
		// for (var i = 0; i < lines.length; i++) {
			// blacklist_domain_list += lines[i];
			// blacklist_domain_list += ",";
		// }
		// blacklist_domain_list = blacklist_domain_list.substring(0,blacklist_domain_list.length-1);
		// var data = 
		// $("#blacklist_domain").html(blacklist_domain_list);
		// console.log(blacklist_domain_list);
		// alert(blacklist_domain_list);
		// console.log("Hello World");
		// $.ajax({
		   // post: $this.attr('action'),
		   // data: yourData,
		   // ...
		// })
		// var VAL = $('#blacklist_domain').val();
		// alert(VAL);

        // var email = new RegExp('^[A-Z0-9\.\_\-\,]+$');

        // if (email.test(VAL)) {
            // alert('Great, you entered an E-Mail-address');
        // } else {
			// alert('Invalid character found');
		// }
	// });
	var regex = /^[a-zA-Z0-9][a-zA-Z0-9-_]{1,61}[a-zA-Z0-9](?:\.[a-zA-Z]{2,})+$/;
	
	$('#blacklist_domain').tagsInput({
		defaultText: '',
		delimiter: ';',
		width: '400px',
		pattern: regex,
		// onChange: function(obj, tag){
			// if($('#frontend_ip_whitelist').tagExist(tag)){
				// $('#frontend_ip_blacklist').removeTag(tag);
			// }
		// }
	});
	
});