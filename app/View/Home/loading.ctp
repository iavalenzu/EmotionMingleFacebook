<html>
    <head>
        <style>
            
            html { 
               width:100%; 
               height:100%; 
               background:url(http://www.denomades.com/images/loading_search.gif) center center no-repeat;
            }            
            
        </style>
        
        <script>
            
            <?php if($redirect_url): ?>

                setTimeout(function()
                {
                    this.document.location.href = "<?php echo $redirect_url; ?>";
                }, 1000); 


            <?php endif; ?>
            
        
        </script>
        
    </head>
    <body>
    </body>
</html>
