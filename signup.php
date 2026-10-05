<?php
require_once __DIR__ . '/includes/functions.php';

// signup is checked before the page is drawn, so an error can be shown inside the form
$signup_error='';
$signup_done=false;
$ux_name='';
$ux_email='';

if(isset($_POST['btn_signup'])){

    $ux_name=trim($_POST['name'] ?? '');
    $ux_email=trim($_POST['email'] ?? '');
    $ux_pass=$_POST['password'] ?? '';
    $ux_recheck=$_POST['re_pass'] ?? '';

    if($ux_name=='' || $ux_email=='' || $ux_pass==''){
        $signup_error='Please fill in all fields.';
    }
    else if(!filter_var($ux_email, FILTER_VALIDATE_EMAIL)){
        $signup_error='Please enter a valid email address.';
    }
    else if(strlen($ux_pass)<6){
        $signup_error='Password must be at least 6 characters.';
    }
    else if($ux_pass!==$ux_recheck){
        $signup_error='Password Did Not Match!';
    }
    else if(db_one("select id from user_reg_login where email=? limit 1", 's', [$ux_email])){
        $signup_error='An account with this email already exists. Please sign in instead.';
    }
    else{
        // column list is needed since migration 001 added more columns to user_reg_login
        // the password stays plain text because signin.php compares it that way
        $ux_insert=db_query("insert into user_reg_login (name,email,password) values (?,?,?)", 'sss', [$ux_name,$ux_email,$ux_pass]);

        if($ux_insert>0){
            $signup_done=true;
        }
        else{
            $signup_error='Something Went Wrong Try Another Time!';
        }
    }
}

include('header.php');
?>



<div class="sign section--bg" data-bg="img/section/section.jpg">
		<div class="container">
			<div class="row">
				<div class="col-12">
					<div class="sign__content">
						<!-- registration form -->
						<form action="" class="sign__form" method="post">

                            <div class="sign__group">
                                  <h3 class="text-white">Registration</h3>
							</div>

							<?php if($signup_error!=''){ ?>
							<div class="sign__group">
								<p style="color:#ff5860;margin:0;"><?php echo e($signup_error); ?></p>
							</div>
							<?php } ?>

							<div class="sign__group">
								<input type="text" class="sign__input" placeholder="Full Name"  name="name" value="<?php echo e($ux_name); ?>" required >
							</div>

							<div class="sign__group">
								<input type="email" class="sign__input" placeholder="Email"  name="email" value="<?php echo e($ux_email); ?>" required >
							</div>

							<div class="sign__group">
								<input type="password" class="sign__input" placeholder="Password (min 6 characters)"  name="password" minlength="6" required >
							</div>

                            <div class="sign__group">
								<input type="password" class="sign__input" placeholder="Confirm Password"  name="re_pass" required >
							</div>

							<div class="sign__group sign__group--checkbox">
								<input id="remember" name="remember" type="checkbox" checked="checked">
								<label for="remember">I agree to the <a href="#">Privacy Policy</a></label>
							</div>

							<button class="sign__btn" type="submit" name="btn_signup">Sign up</button>

							<span class="sign__text">Already have an account? <a href="signin.php">Sign in!</a></span>
						</form>
						<!-- registration form -->
					</div>
				</div>
			</div>
		</div>
	</div>

<?php if($signup_done){ ?>
	<script>
       alert("Now Please Log In With Your Email and Password")
	</script>

	<meta http-equiv="refresh" content="0; URL=signin.php"/>
<?php } ?>



<?php
include('footer.php');
?>
