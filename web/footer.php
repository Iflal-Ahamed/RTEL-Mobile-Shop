<style>
  .footer-modern {
    background: linear-gradient(180deg, #ffffff, #f6f6f6);
    color: #202020;
    border-top: 1px solid #e7e7e7;
    box-shadow: 0 -10px 22px rgba(0, 0, 0, 0.08);
  }

  .footer-modern .ftco-heading-2 {
    color: #111 !important;
    font-size: 18px;
    font-weight: 700;
    margin-bottom: 14px;
    letter-spacing: .3px;
  }

  .footer-modern .ftco-footer-widget {
    background: #fff;
    border: 1px solid #e8e8e8;
    border-radius: 14px;
    padding: 16px 18px;
    height: 100%;
    box-shadow: 0 8px 18px rgba(0, 0, 0, 0.05);
  }

  .footer-modern a {
    color: #1a1a1a !important;
    transition: .2s ease;
  }

  .footer-modern a:hover {
    color: #000 !important;
    text-decoration: none;
    transform: translateX(2px);
  }

  .footer-modern .mouse-icon {
    background: #fff;
    border: 1px solid #e3e3e3;
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
  }

  .footer-modern .mouse-wheel span {
    color: #111;
  }

  .footer-bottom-note {
    border-top: 1px solid #e3e3e3;
    padding-top: 16px;
    color: #444;
    font-size: 14px;
  }

</style>
<section id="feedback-section" class="ftco-section img ftco-no-pt ftco-no-pb py-5 ftco-animate" style="background-image: url('../images/banner3.jpg'); background-size: cover;">
		<div class="container py-4">
			<div class="row d-flex align-items-center justify-content-center py-5">
				<div class="col-md-5">
					<h1 style="font-weight: bold; color:white;" class="mb-0"><span data-i18n="leave_your">Leave your</span> <br> <span data-i18n="comments">Comments!</span></h1>
				</div>
				
				<div class="col-md-7">
					<div class="card shadow-sm border-0">
						<div class="card-body p-4" >
							<?php
								$commentNotice = null;
								if (isset($_SESSION['comment_notice']) && is_array($_SESSION['comment_notice'])) {
									$commentNotice = [
										'type' => ($_SESSION['comment_notice']['type'] ?? 'success'),
										'text' => ($_SESSION['comment_notice']['text'] ?? '')
									];
									unset($_SESSION['comment_notice']);
								}
								$feedbackNameDefault = trim((string)($_SESSION['user_name'] ?? ''));
								$feedbackEmailDefault = trim((string)($_SESSION['user_email'] ?? ''));
							?>
							<form id="feedbackForm" class="form" method="POST" action="save_comment.php">
								<div class="form-group mb-3">
									<input type="text" class="form-control" placeholder="Your Name" data-i18n-placeholder="placeholder_your_name" style="font-size:15px;" id="name" name="name" value="<?php echo htmlspecialchars($feedbackNameDefault, ENT_QUOTES, 'UTF-8'); ?>" data-default-value="<?php echo htmlspecialchars($feedbackNameDefault, ENT_QUOTES, 'UTF-8'); ?>">
								</div>
								<div class="form-group mb-3">
									<input type="email" class="form-control" placeholder="Your Email (optional)" style="font-size:15px;" id="feedback_email" name="email" value="<?php echo htmlspecialchars($feedbackEmailDefault, ENT_QUOTES, 'UTF-8'); ?>" data-default-value="<?php echo htmlspecialchars($feedbackEmailDefault, ENT_QUOTES, 'UTF-8'); ?>">
								</div>
								<div class="form-group mb-3">
									<textarea  class="form-control" rows="3" placeholder="Write your thoughts..." data-i18n-placeholder="placeholder_write_thoughts" style="font-size:15px;" id="comment" name="comment"></textarea>
								</div>
								<div class=" d-flex justify-content-end">
									<button type="submit" class="btn btn-dark px-4 py-3" name="btnComment" data-i18n="send_comment">Send Comment</button>
								</div>
							</form>
							<script>
							(function () {
								var form = document.getElementById("feedbackForm");
								if (!form || form.dataset.bound === "1") return;
								form.dataset.bound = "1";

								function showFeedbackSwal(type, text) {
									if (!window.Swal) return;
									var isError = type === "error";
									Swal.fire({
										icon: isError ? "error" : "success",
										title: isError ? "Submission Failed" : "Thank You!",
										text: text || (isError ? "Unable to submit feedback." : "Your feedback has been sent successfully."),
										confirmButtonColor: "#111",
										background: "#fff"
									});
								}

								form.addEventListener("submit", function (e) {
									e.preventDefault();
									e.stopPropagation();
									var formData = new FormData(form);
									formData.append("ajax", "1");
									if (!formData.get("btnComment")) {
										formData.append("btnComment", "1");
									}

									fetch("save_comment.php", {
										method: "POST",
										headers: { "X-Requested-With": "XMLHttpRequest" },
										body: formData
									})
									.then(function (res) { return res.json(); })
									.then(function (data) {
										if (!data) return;
										showFeedbackSwal(data.type || (data.success ? "success" : "error"), data.text || "Unable to submit feedback.");
										if (data.success) {
											form.reset();
											form.querySelectorAll("[data-default-value]").forEach(function (el) {
												el.value = el.getAttribute("data-default-value") || "";
											});
										}
									})
									.catch(function () {
										showFeedbackSwal("error", "Request failed while submitting feedback.");
									});
									return false;
								}, true);

								var initialNotice = <?php echo json_encode($commentNotice, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
								if (initialNotice && typeof initialNotice === "object") {
									showFeedbackSwal(String(initialNotice.type || "success"), String(initialNotice.text || ""));
								}
							})();
							</script>
						</div>
					</div>
				</div>
			</div>
		</div>
	</section>
	
<footer class="ftco-footer ftco-section footer-modern">
		<div class="container">
			<div class="row">
				<div class="mouse">
					<a href="#" class="mouse-icon">
						<div class="mouse-wheel"><span class="ion-ios-arrow-up"></span></div>
					</a>
				</div>
			</div>
			<div class="row mb-5">
				<div class="col-md" id="contactContainer">
					<!-- contact info will load here -->
				</div>
				<div class="col-md">
					<div class="ftco-footer-widget mb-4 ml-md-5">
						<h2 class="ftco-heading-2" data-i18n="footer_menu">📌 Menu</h2>
						<ul class="list-unstyled">
					
							<li><a href="shop.php" class="py-2 d-block" data-i18n="nav_shop">Shop</a></li>
							<li><a href="cart.php" class="py-2 d-block" data-i18n="nav_cart">Cart</a></li>
							<li><a href="wishlist.php" class="py-2 d-block" data-i18n="nav_wishlist">Wishlist</a></li>
							</ul>
					</div>
				</div>
				<div class="col-md-4">
					<div class="ftco-footer-widget mb-4">
						<h2 class="ftco-heading-2" data-i18n="footer_help">🛟 Help</h2>
						<div class="d-flex">
							<ul class="list-unstyled mr-l-5 pr-l-3 mr-4">
								<li><a href="chat.php" class="py-2 d-block" data-i18n="nav_ai_assistant">AI Assistant 🤖</a></li>
								<li><a href="privacy_policy.php" class="py-2 d-block" data-i18n="footer_privacy">Privacy Policy</a></li>
							<li><a href="terms_conditions.php" class="py-2 d-block" data-i18n="footer_terms">Terms &amp; Conditions</a></li>
						
							</ul>
						</div>
					</div>
				</div>
			</div>
			<div class="row">
				<div class="col-md-12 text-center footer-bottom-note">
				<p><span data-i18n="copyright">Copyright</span> &copy;<script>document.write(new Date().getFullYear());</script> <span data-i18n="all_rights_reserved">All rights reserved</span></p>
				</div>
			</div>
		</div>
	</footer>

	<!-- loader -->
  <div id="ftco-loader" class="show fullscreen"><svg class="circular" width="48px" height="48px"><circle class="path-bg" cx="24" cy="24" r="22" fill="none" stroke-width="4" stroke="#eeeeee"/><circle class="path" cx="24" cy="24" r="22" fill="none" stroke-width="4" stroke-miterlimit="10" stroke="#000000"/></svg></div>

  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <script src="js/jquery.min.js"></script>
  <script src="js/jquery-migrate-3.0.1.min.js"></script>
  <script src="js/popper.min.js"></script>
  <script src="js/bootstrap.min.js"></script>
  <script src="js/jquery.easing.1.3.js"></script>
  <script src="js/jquery.waypoints.min.js"></script>
  <script src="js/jquery.stellar.min.js"></script>
  <script src="js/owl.carousel.min.js"></script>
  <script src="js/jquery.magnific-popup.min.js"></script>
  <script src="js/aos.js"></script>
  <script src="js/jquery.animateNumber.min.js"></script>
  <script src="js/bootstrap-datepicker.js"></script>
  <script src="js/scrollax.min.js"></script>
  <script src="js/main.js"></script>
  <script src="js/theme-toggle.js"></script>
    <script src="ajax.js?v=20260501-1"></script>
  </body>
</html>