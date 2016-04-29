<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <title>404</title>
    <meta name="msapplication-TileColor" content="#5bc0de" />
    <meta name="msapplication-TileImage" content="assets/img/metis-tile.png" />

    <!-- Bootstrap -->
    <link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.3.1/css/bootstrap.min.css">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/font-awesome/4.2.0/css/font-awesome.min.css">

    <!-- Metis core stylesheet -->
    <link rel="stylesheet" href="assets/css/main.min.css">
  </head>
  <body class="error">
    <div class="container">
      <div class="col-lg-8 col-lg-offset-2 text-center">
        <div class="logo">
          <h1>404</h1>
        </div>
        <p class="lead text-muted">Oops, an error has occurred. Forbidden!</p>
        <div class="clearfix"></div>
        <div class="col-lg-6 col-lg-offset-3">
          <!--<form action="index.html">-->
          <form action="#">
            <div class="input-group">
              <input type="text" placeholder="search ..." class="form-control">
              <span class="input-group-btn">
              <button class="btn btn-default" type="button"><i class="fa fa-search"></i></button>
            </span> 
            </div>
          </form>
        </div>
        <div class="clearfix"></div>
        <br>
        <div class="col-lg-6 col-lg-offset-3">
          <div class="btn-group btn-group-justified">
          <a href="<?php echo base_url('home');?>" class="btn btn-info">Return Dashboard</a> 
          <a href="<?php echo base_url('home');?>" class="btn btn-warning">Return Website</a> 
          </div>
        </div>
      </div><!-- /.col-lg-8 col-offset-2 -->
    </div>
  </body>
</html>