EcwidApp.init({
   app_id: "custom-app-15083087-15", // use your application namespace
   autoloadedflag: true, 
   autoheight: true
});

var websiteURL = "https://unzerecwid.mavenhostingservice.com/";
var actionUrl = websiteURL+'includes/commonFunctions.php'

//Default hide success message
$(".success_message").hide();
$(".delete_message").hide();
$(".failed_message").hide();
$(".loader").hide();

//Submit the configurations form 
$("#configurationForm").submit(function(e) {
    e.preventDefault();
    $(".loader").show();
    var form = $(this);
    $.ajax({
        type: "POST",
        url: actionUrl,
        data: form.serialize(),
        success: function(data){
            if(data != 0){
                $(".loader").hide();
                $(".success_message").show();
                setTimeout(function() {
                  $(".success_message").hide();
                }, 1500);
            }else{
                $(".loader").hide();
                $(".failed_message").show();
                setTimeout(function() {
                  $(".failed_message").hide();
                }, 1500);
            }
        }
    });
});

//Add Webhook 
$("#add-webhook-button").click(function(e) {
    e.preventDefault();
    $(".loader").show();
    var webhookURL = $("#webhookUrl").val();
    var ecwidStoreId = $("#storeId").val();
    $.ajax({
        type: "POST",
        url: actionUrl,
        data: {
            action: "webhook_add",
            storeId: ecwidStoreId,
            webhookURL: webhookURL
        },
        success: function(data){
             if(data != 0){
                $(".loader").hide();
                $(".success_message").show();
                setTimeout(function() {
                  location.reload();
                }, 1500);
            }else{
                $(".loader").hide();
                $(".failed_message").show();
                setTimeout(function() {
                  location.reload();
                }, 1500);
            }
        }
    });
});

//Delete Webhook
$("#delete-webhook").click(function(e) {
    e.preventDefault();
    var deleteWebhook = confirm("Are you sure you want to delete this webhook?");
    if (deleteWebhook) {
       var dataValue = $(this).data('value');
        $.ajax({
            type: "POST",
            url: actionUrl,
            data: {
                action: "webhook_delete",
                id: dataValue
            },
            success: function(data){
                 if(data != 0){
                    $(".loader").hide();
                    $(".delete_message").show();
                    setTimeout(function() {
                      location.reload();
                    }, 1500);
                }else{
                    $(".loader").hide();
                    $(".failed_message").show();
                    setTimeout(function() {
                      location.reload();
                    }, 1500);
                }
            }
        });
    }else{
        return false;
    }
});
