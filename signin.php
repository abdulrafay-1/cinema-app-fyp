<?php
include('header.php');
?>


<div class="sign section--bg" data-bg="img/section/section.jpg">
		<div class="container">
			<div class="row">
				<div class="col-12">
					<div class="sign__content">
						<!-- authorization form -->
						<form action="" class="sign__form" method="post">
						<div class="sign__group">
                                  <h3 class="text-white">Login In</h3>
							</div>

							<div class="sign__group">
								<input type="text" class="sign__input" placeholder="Email" name="email" >
							</div>

							<div class="sign__group">
								<input type="password" class="sign__input" placeholder="Password" name="password" >
							</div>

							<div class="sign__group sign__group--checkbox">
								<input id="remember" name="remember" type="checkbox" checked="checked">
								<label for="remember">Remember Me</label>
							</div>
							
							<button class="sign__btn" type="submit" name="btn_uxlogin" >Sign in</button>

							<span class="sign__text">Don't have an account? <a href="signup.php">Sign up!</a></span>

							<span class="sign__text"><a href="signup.php">Forgot password?</a></span>
						</form>
						<!-- end authorization form -->
					</div>
				</div>
			</div>
		</div>
	</div>

<?php 


if(isset($_POST['btn_uxlogin'])){


$ux_login_email=$_POST['email'];
$ux_login_password=$_POST['password'];


require_once __DIR__ . '/includes/functions.php';

// fetching user data from database for login check "select query"
// fetching user data from data base
$fetch_ux_login_check=db_one("select id,name,email,status from user_reg_login where email=? and password=? limit 1", 'ss', [$ux_login_email, $ux_login_password]);


// if else condition for user for $session
if($fetch_ux_login_check && $fetch_ux_login_check["status"]==='Blocked'){

	echo '<script>alert("Your account has been blocked. Please contact support for help.")</script>';

}
elseif($fetch_ux_login_check){

// creating session for logout
	$_SESSION["ux"]=$fetch_ux_login_check["name"];
	$_SESSION["ux_email"]=$fetch_ux_login_check["email"];
	$_SESSION["ux_id"]=(int)$fetch_ux_login_check["id"];




	$after_login=isset($_SESSION['after_login']) ? $_SESSION['after_login'] : '';
	unset($_SESSION['after_login']);
	if(!preg_match('#^/[^/\\\\]#',$after_login)){
		$after_login='index.php';
	}

	echo
	"
	<script>window.location.assign(".json_encode($after_login).")</script>
	";

}
else
        {
			echo '<script>alert("Invalid Email Or Password!")</script>';

        }

}


?>




<?php
include('footer.php');
?>