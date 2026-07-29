document.addEventListener("DOMContentLoaded", function () {

    // --- Province → District wiring (register page only) ---
    const provinceSelect = document.getElementById("province");
    if (provinceSelect) {
        loadDistricts();                                      // populate on first load
        provinceSelect.addEventListener("change", loadDistricts); // update on change
    }

    // --- Run each loader only if its container exists on this page ---
    if (document.getElementById("searchInput"))         liveSearch();
    if (document.getElementById("categoryContainer"))   loadCategories();
    if (document.getElementById("brandContainer"))      loadBrands();
    if (document.getElementById("couponContainer"))     loadCoupons();
    if (document.getElementById("promotionContainer"))  loadPromotions();
    if (document.getElementById("personalizedContainer")) loadPersonalized();
    if (document.getElementById("newarrivalsContainer"))loadNewArrivals();
    if (document.getElementById("onsaleContainer"))     loadOnsales();
    if (document.getElementById("bundleContainer"))     loadBundles();
    if (document.getElementById("commentContainer"))    loadComments();
    if (document.getElementById("contactContainer"))    loadContacts();
    if (document.getElementById("brandProductsContainer")) loadBrandProducts();
    initFeedbackForm();
    initProductActions();
    initCartQuantityControls();
    initBundleQuantityControls();
    refreshNavCounts();
    initCouponCopy();

});

/* ------------------------------------------------------------------ */
/*  Province → District                                                 */
/* ------------------------------------------------------------------ */
function loadDistricts() {

    const provinceSelect = document.getElementById("province");
    const districtSelect = document.getElementById("district");

    if (!provinceSelect || !districtSelect) return;   // not on this page

    const data = {
        western:       ["Colombo", "Gampaha", "Kalutara"],
        central:       ["Kandy", "Matale", "Nuwara Eliya"],
        southern:      ["Galle", "Matara", "Hambantota"],
        northern:      ["Jaffna", "Kilinochchi", "Mannar", "Mullaitivu", "Vavuniya"],
        eastern:       ["Trincomalee", "Batticaloa", "Ampara"],
        northwestern:  ["Kurunegala", "Puttalam"],
        northcentral:  ["Anuradhapura", "Polonnaruwa"],
        uva:           ["Badulla", "Monaragala"],
        sabaragamuwa:  ["Kegalle", "Ratnapura"]
    };
    const districtLabels = {
        ta: {
            Colombo: "கொழும்பு",
            Gampaha: "கம்பஹா",
            Kalutara: "களுத்துறை",
            Kandy: "கண்டி",
            Matale: "மாத்தளை",
            "Nuwara Eliya": "நுவரெலியா",
            Galle: "காலி",
            Matara: "மாத்தறை",
            Hambantota: "அம்பாந்தோட்டை",
            Jaffna: "யாழ்ப்பாணம்",
            Kilinochchi: "கிளிநொச்சி",
            Mannar: "மன்னார்",
            Mullaitivu: "முல்லைத்தீவு",
            Vavuniya: "வவுனியா",
            Trincomalee: "திருகோணமலை",
            Batticaloa: "மட்டக்களப்பு",
            Ampara: "அம்பாறை",
            Kurunegala: "குருநாகல்",
            Puttalam: "புத்தளம்",
            Anuradhapura: "அனுராதபுரம்",
            Polonnaruwa: "பொலன்னறுவை",
            Badulla: "பதுளை",
            Monaragala: "மொணராகலை",
            Kegalle: "கேகாலை",
            Ratnapura: "இரத்தினபுரி"
        },
        si: {
            Colombo: "කොළඹ",
            Gampaha: "ගම්පහ",
            Kalutara: "කළුතර",
            Kandy: "මහනුවර",
            Matale: "මාතලේ",
            "Nuwara Eliya": "නුවරඑළිය",
            Galle: "ගාල්ල",
            Matara: "මාතර",
            Hambantota: "හම්බන්තොට",
            Jaffna: "යාපනය",
            Kilinochchi: "කිලිනොච්චි",
            Mannar: "මන්නාරම",
            Mullaitivu: "මුලතිව්",
            Vavuniya: "වව්නියාව",
            Trincomalee: "ත්‍රිකුණාමලය",
            Batticaloa: "මඩකලපුව",
            Ampara: "අම්පාර",
            Kurunegala: "කුරුණෑගල",
            Puttalam: "පුත්තලම",
            Anuradhapura: "අනුරාධපුරය",
            Polonnaruwa: "පොළොන්නරුව",
            Badulla: "බදුල්ල",
            Monaragala: "මොණරාගල",
            Kegalle: "කෑගල්ල",
            Ratnapura: "රත්නපුර"
        }
    };
    const selectDistrictLabel = {
        en: "Select District",
        ta: "மாவட்டத்தைத் தேர்ந்தெடுக்கவும்",
        si: "දිස්ත්‍රික්කය තෝරන්න"
    };

    const province = provinceSelect.value;
    const currentLang = (localStorage.getItem("site_lang") || "en").toLowerCase();
    const localizedDistricts = districtLabels[currentLang] || {};

    districtSelect.innerHTML = "<option value=''>" + (selectDistrictLabel[currentLang] || selectDistrictLabel.en) + "</option>";
    districtSelect.disabled = !province;              // disable until province chosen

    if (data[province]) {
        const selectedDistrict = districtSelect.getAttribute("data-selected-district") || "";
        data[province].forEach(function (d) {
            const option = document.createElement("option");
            option.value = d;
            option.textContent = localizedDistricts[d] || d;
            if (selectedDistrict && selectedDistrict === d) {
                option.selected = true;
            }
            districtSelect.appendChild(option);
        });
    }
}

/* ------------------------------------------------------------------ */
/*  Live Search                                                         */
/* ------------------------------------------------------------------ */
function liveSearch() {
    const input   = document.getElementById("searchInput");
    const dropdown = document.getElementById("searchDropdown");

    if (!input || !dropdown) return;
    if (input.dataset.bound === "1") return;
    input.dataset.bound = "1";

    const runSearch = function () {
        const keyword = input.value.trim();

        if (keyword.length === 0) {
            dropdown.innerHTML = "";
            dropdown.style.display = "none";
            return;
        }

        fetch("process.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: "action=live_search&keyword=" + encodeURIComponent(keyword)
        })
        .then(res => res.text())
        .then(data => {
            dropdown.innerHTML = data;
            dropdown.style.position = "absolute";
            dropdown.style.left = "0";
            dropdown.style.top = "calc(100% + 8px)";
            dropdown.style.width = "100%";
            dropdown.style.zIndex = "99999";
            dropdown.style.display = "block";
        })
        .catch(err => console.error("Live search error:", err));
    };

    input.addEventListener("input", runSearch);
    input.addEventListener("keyup", runSearch);
    input.addEventListener("keydown", function (e) {
        if (e.key !== "Enter") return;
        const keyword = input.value.trim();
        if (!keyword) return;
        // If user presses Enter without choosing a suggestion,
        // open search results page with related products.
        e.preventDefault();
        window.location.href = "shop.php?q=" + encodeURIComponent(keyword);
    });

    document.addEventListener("click", function (event) {
        if (!input.contains(event.target) && !dropdown.contains(event.target)) {
            dropdown.style.display = "none";
        }
    });

}

/* ------------------------------------------------------------------ */
/*  Generic fetch helper — avoids repeating try/catch everywhere       */
/* ------------------------------------------------------------------ */
function ajaxLoad(action, containerId, callback) {

    const container = document.getElementById(containerId);
    if (!container) return;

    fetch("process.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "action=" + action
    })
    .then(res => res.text())
    .then(data => {
        container.innerHTML = data;
        if (typeof callback === "function") callback();
    })
    .catch(err => console.error("AJAX Error [" + action + "]:", err));
}

/* ------------------------------------------------------------------ */
/*  Individual loaders (all use the helper)                            */
/* ------------------------------------------------------------------ */
function loadCategories()   { ajaxLoad("load_categories",  "categoryContainer"); }
function loadBrands()       { ajaxLoad("load_brands",      "brandContainer", initBrandCarousel); }
function loadNewArrivals()  { ajaxLoad("load_newarrivals", "newarrivalsContainer"); }
function loadOnsales()      { ajaxLoad("load_onsales",     "onsaleContainer"); }
function loadBundles()      { ajaxLoad("load_bundles_home","bundleContainer"); }
function loadContacts()     { ajaxLoad("load_contactinfo", "contactContainer"); }
function loadBrandProducts(){ ajaxLoad("load_brandProducts","brandProductsContainer"); }
function loadCoupons()      { ajaxLoad("load_coupons", "couponContainer", initCouponCarousel); }
function loadPromotions()   { ajaxLoad("load_promotions", "promotionContainer", initPromotionCarousel); }
function loadPersonalized() {
    ajaxLoad("load_personalized", "personalizedContainer", function () {
        try {
            const meta = document.getElementById("personalizedMeta");
            if (!meta) return;
            const mode = String(meta.getAttribute("data-mode") || "fallback");
            const provider = String(meta.getAttribute("data-provider") || "");
            console.info("[R-TEL Personalization] mode:", mode, provider ? ("provider: " + provider) : "");
        } catch (_) {}
    });
}

function loadComments() {

    const container = document.getElementById("commentContainer");
    if (!container) return;

    fetch("process.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "action=load_comments"
    })
    .then(res => res.text())
    .then(data => {

        container.innerHTML = data;

        setTimeout(() => {
            const owl = $(".carousel-testimony");
            if (owl.hasClass("owl-loaded")) {
                owl.trigger("destroy.owl.carousel");
                owl.removeClass("owl-loaded");
                owl.find(".owl-stage-outer").children().unwrap();
            }

            owl.owlCarousel({
                loop: true,
                autoplay: true,
                autoplayTimeout: 4200,
                autoplayHoverPause: true,
                margin: 24,
                dots: true,
                nav: true,
                navText: [
                    "<span class='ion-ios-arrow-back'></span>",
                    "<span class='ion-ios-arrow-forward'></span>"
                ],
                slideBy: "page",
                smartSpeed: 850,
                mouseDrag: true,
                touchDrag: true,
                pullDrag: true,
                responsive: {
                    0: { items: 1, slideBy: 1 },
                    768: { items: 2, slideBy: 2 },
                    992: { items: 3, slideBy: 3 }
                }
            });
        }, 300);

    })
    .catch(err => console.error("AJAX Error [load_comments]:", err));
}

function initCouponCarousel() {
    const owl = $("#couponContainer");
    if (!owl.length || typeof owl.owlCarousel !== "function") return;

    if (owl.hasClass("owl-loaded")) {
        owl.trigger("destroy.owl.carousel");
        owl.removeClass("owl-loaded");
        owl.find(".owl-stage-outer").children().unwrap();
    }

    owl.owlCarousel({
        loop: false,
        autoplay: true,
        autoplayTimeout: 3800,
        autoplayHoverPause: true,
        margin: 14,
        dots: true,
        nav: true,
        navText: [
            "<span class='ion-ios-arrow-back'></span>",
            "<span class='ion-ios-arrow-forward'></span>"
        ],
        smartSpeed: 700,
        responsive: {
            0: { items: 1 },
            576: { items: 2 },
            992: { items: 3 }
        }
    });
}

function initPromotionCarousel() {
    const owl = $("#promotionContainer");
    if (!owl.length || typeof owl.owlCarousel !== "function") return;

    if (owl.hasClass("owl-loaded")) {
        owl.trigger("destroy.owl.carousel");
        owl.removeClass("owl-loaded");
        owl.find(".owl-stage-outer").children().unwrap();
    }

    owl.owlCarousel({
        loop: false,
        autoplay: true,
        autoplayTimeout: 4200,
        autoplayHoverPause: true,
        margin: 14,
        dots: true,
        nav: true,
        navText: [
            "<span class='ion-ios-arrow-back'></span>",
            "<span class='ion-ios-arrow-forward'></span>"
        ],
        smartSpeed: 700,
        responsive: {
            0: { items: 1 },
            576: { items: 2 },
            992: { items: 3 }
        }
    });
}

function initCouponCopy() {
    if (document.body.dataset.couponCopyBound === "1") return;
    document.body.dataset.couponCopyBound = "1";
    document.addEventListener("click", function (e) {
        const btn = e.target && e.target.closest ? e.target.closest(".js-copy-coupon") : null;
        if (!btn) return;
        const code = (btn.getAttribute("data-code") || "").trim();
        if (!code) return;

        const done = function () {
            const old = btn.textContent;
            btn.textContent = "Copied";
            setTimeout(function () { btn.textContent = old; }, 1400);
        };

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(code).then(done).catch(function () {
                window.prompt("Copy coupon code:", code);
            });
        } else {
            window.prompt("Copy coupon code:", code);
        }
    });
}

function initBrandCarousel() {
    const container = document.getElementById("brandContainer");
    const prevBtn = document.getElementById("brandPrev");
    const nextBtn = document.getElementById("brandNext");
    if (!container || container.dataset.carouselBound === "1") return;
    container.dataset.carouselBound = "1";

    let timer = null;
    const step = 140;

    const start = function () {
        if (timer) return;
        timer = setInterval(function () {
            const maxScroll = container.scrollWidth - container.clientWidth;
            if (maxScroll <= 0) return;
            if (container.scrollLeft + step >= maxScroll) {
                container.scrollTo({ left: 0, behavior: "smooth" });
            } else {
                container.scrollBy({ left: step, behavior: "smooth" });
            }
        }, 2400);
    };

    const stop = function () {
        if (!timer) return;
        clearInterval(timer);
        timer = null;
    };

    container.addEventListener("mouseenter", stop);
    container.addEventListener("mouseleave", start);

    if (prevBtn) {
        prevBtn.addEventListener("click", function () {
            container.scrollBy({ left: -step * 2, behavior: "smooth" });
        });
    }
    if (nextBtn) {
        nextBtn.addEventListener("click", function () {
            container.scrollBy({ left: step * 2, behavior: "smooth" });
        });
    }
    start();
}

function showActionAlert(icon, title) {
    if (typeof Swal === "undefined") {
        window.alert(title);
        return;
    }
    Swal.fire({
        icon: icon,
        title: title,
        toast: true,
        position: "top-end",
        showConfirmButton: false,
        timer: 1600,
        timerProgressBar: true
    });
}

function formatRs(value) {
    return "Rs. " + Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function refreshNavCounts() {
    const cartBadge = document.getElementById("cartCountBadge");
    const wishlistBadge = document.getElementById("wishlistCountBadge");
    if (!cartBadge && !wishlistBadge) return;

    fetch("product_action.php?action=get_counts&ajax=1")
        .then(res => res.json())
        .then(data => {
            if (!data || !data.success) return;
            if (cartBadge) cartBadge.textContent = data.cart_count ?? 0;
            if (wishlistBadge) wishlistBadge.textContent = data.wishlist_count ?? 0;
        })
        .catch(() => {});
}

function sendProductAction(action, productId) {
    return fetch("product_action.php?action=" + encodeURIComponent(action) + "&product_id=" + encodeURIComponent(productId) + "&ajax=1", {
        method: "GET",
        headers: { "X-Requested-With": "XMLHttpRequest" }
    }).then(res => res.json());
}

function sendBundleAction(action, bundleId, variants) {
    let url = "product_action.php?action=" + encodeURIComponent(action) + "&bundle_id=" + encodeURIComponent(bundleId) + "&ajax=1";
    if (variants && typeof variants === "object" && Object.keys(variants).length > 0) {
        url += "&bundle_variants=" + encodeURIComponent(JSON.stringify(variants));
    }
    return fetch(url, {
        method: "GET",
        headers: { "X-Requested-With": "XMLHttpRequest" }
    }).then(res => res.json());
}

function initProductActions() {
    document.addEventListener("click", function (event) {
        const cartBtn = event.target.closest(".js-add-cart, a[href*='product_action.php?action=add_cart']");
        const wishlistBtn = event.target.closest(".js-add-wishlist, a[href*='product_action.php?action=add_wishlist']");
        const bundleCartBtn = event.target.closest(".js-add-bundle-cart");
        const bundleWishBtn = event.target.closest(".js-add-bundle-wishlist");
        if (bundleCartBtn || bundleWishBtn) {
            event.preventDefault();
            const bundleId = (bundleCartBtn || bundleWishBtn).getAttribute("data-bundle-id");
            if (!bundleId) return;
            const bundleAction = bundleCartBtn ? "add_bundle_cart" : "add_bundle_wishlist";
            const variants = {};
            document.querySelectorAll('.js-bundle-item[data-bundle-id="' + bundleId + '"]').forEach(function (row) {
                const pid = String(row.getAttribute("data-product-id") || "").trim();
                if (!pid) return;
                const colorSel = row.querySelector(".js-bundle-color");
                const storageSel = row.querySelector(".js-bundle-storage");
                const genericSel = row.querySelector(".js-bundle-variant");
                const parts = [];
                const colorVal = colorSel ? String(colorSel.value || "").trim() : "";
                const storageVal = storageSel ? String(storageSel.value || "").trim() : "";
                const genericVal = genericSel ? String(genericSel.value || "").trim() : "";
                if (colorVal) parts.push("Color: " + colorVal);
                if (storageVal) parts.push("Storage: " + storageVal);
                if (genericVal && parts.length === 0) parts.push(genericVal);
                if (parts.length > 0) variants[pid] = parts.join(" | ");
            });
            document.querySelectorAll('.js-bundle-variant[data-bundle-id="' + bundleId + '"]').forEach(function (sel) {
                const pid = String(sel.getAttribute("data-product-id") || "").trim();
                const val = String(sel.value || "").trim();
                if (pid && val && !variants[pid]) variants[pid] = val;
            });
            sendBundleAction(bundleAction, bundleId, variants)
                .then(data => {
                    if (data.redirect) {
                        window.location.href = data.redirect;
                        return;
                    }
                    if (!data.success) {
                        showActionAlert("error", data.message || "Something went wrong");
                        return;
                    }
                    const cartBadge = document.getElementById("cartCountBadge");
                    const wishlistBadge = document.getElementById("wishlistCountBadge");
                    if (cartBadge && typeof data.cart_count !== "undefined") cartBadge.textContent = data.cart_count;
                    if (wishlistBadge && typeof data.wishlist_count !== "undefined") wishlistBadge.textContent = data.wishlist_count;
                    showActionAlert("success", data.message || "Done");
                })
                .catch(() => showActionAlert("error", "Request failed"));
            return;
        }
        const button = cartBtn || wishlistBtn;
        if (!button) return;

        event.preventDefault();
        let productId = button.getAttribute("data-product-id");
        if (!productId) {
            try {
                const url = new URL(button.getAttribute("href"), window.location.origin + "/");
                productId = url.searchParams.get("product_id");
            } catch (e) {
                productId = null;
            }
        }
        if (!productId) return;

        const action = cartBtn ? "add_cart" : "add_wishlist";
        sendProductAction(action, productId)
            .then(data => {
                if (data.redirect) {
                    window.location.href = data.redirect;
                    return;
                }
                if (!data.success) {
                    showActionAlert("error", data.message || "Something went wrong");
                    return;
                }
                const cartBadge = document.getElementById("cartCountBadge");
                const wishlistBadge = document.getElementById("wishlistCountBadge");
                if (cartBadge && typeof data.cart_count !== "undefined") cartBadge.textContent = data.cart_count;
                if (wishlistBadge && typeof data.wishlist_count !== "undefined") wishlistBadge.textContent = data.wishlist_count;

                showActionAlert("success", data.message || "Done");
            })
            .catch(() => showActionAlert("error", "Request failed"));
    });
}

function initCartQuantityControls() {
    document.addEventListener("click", function (event) {
        const button = event.target.closest(".js-cart-qty-btn");
        if (!button) return;
        event.preventDefault();

        const cartId = button.getAttribute("data-cart-id");
        const qtyEl = document.getElementById("cart-qty-" + cartId);
        if (!cartId || !qtyEl) return;

        const stock = parseInt(button.getAttribute("data-stock") || "0", 10);
        const currentQty = parseInt(qtyEl.textContent || "1", 10);
        const isIncrease = button.classList.contains("js-cart-inc");
        let nextQty = isIncrease ? currentQty + 1 : currentQty - 1;
        if (nextQty < 1) nextQty = 1;
        if (stock > 0 && nextQty > stock) {
            showActionAlert("warning", "Maximum stock available: " + stock);
            nextQty = stock;
        }
        if (nextQty === currentQty) return;

        fetch(
            "product_action.php?action=update_cart_qty&ajax=1&cart_id=" +
            encodeURIComponent(cartId) +
            "&quantity=" + encodeURIComponent(nextQty),
            { method: "GET", headers: { "X-Requested-With": "XMLHttpRequest" } }
        )
            .then(res => res.json())
            .then(data => {
                if (!data) return;
                if (data.redirect) {
                    window.location.href = data.redirect;
                    return;
                }
                if (!data.success) {
                    showActionAlert("error", data.message || "Unable to update quantity");
                    return;
                }
                qtyEl.textContent = data.quantity;
                const lineEl = document.getElementById("cart-total-" + cartId);
                if (lineEl && typeof data.line_total !== "undefined") {
                    lineEl.textContent = formatRs(data.line_total);
                }
                const checkboxList = document.querySelectorAll(".js-cart-select");
                if (typeof data.line_total !== "undefined" && checkboxList.length) {
                    checkboxList.forEach(function (cb) {
                        if (cb.value === String(cartId)) {
                            cb.setAttribute("data-line-total", Number(data.line_total).toFixed(2));
                            cb.dispatchEvent(new Event("change"));
                        }
                    });
                }
                const subEl = document.getElementById("cart-subtotal");
                const grandEl = document.getElementById("cart-grand-total");
                if (subEl && typeof data.subtotal !== "undefined") subEl.textContent = formatRs(data.subtotal);
                if (grandEl && typeof data.subtotal !== "undefined") {
                    const shippingFee = Number(grandEl.getAttribute("data-shipping-fee") || "0");
                    grandEl.textContent = formatRs(Number(data.subtotal) + shippingFee);
                }
                if (data.message) {
                    showActionAlert("success", data.message);
                }
            })
            .catch(() => showActionAlert("error", "Request failed"));
    });
}

function initBundleQuantityControls() {
    document.addEventListener("click", function (event) {
        const button = event.target.closest(".js-bundle-qty-btn");
        if (!button) return;
        event.preventDefault();

        const cartId = button.getAttribute("data-cart-id");
        const qtyEl = document.getElementById("cart-qty-" + cartId);
        if (!cartId || !qtyEl) return;

        const currentQty = parseInt(qtyEl.textContent || "1", 10);
        const isIncrease = button.classList.contains("js-bundle-inc");
        let nextQty = isIncrease ? currentQty + 1 : currentQty - 1;
        if (nextQty < 1) nextQty = 1;
        if (nextQty === currentQty) return;

        fetch(
            "product_action.php?action=update_bundle_qty&ajax=1&cart_bundle_id=" +
            encodeURIComponent(cartId) +
            "&quantity=" + encodeURIComponent(nextQty),
            { method: "GET", headers: { "X-Requested-With": "XMLHttpRequest" } }
        )
            .then(res => res.json())
            .then(data => {
                if (!data) return;
                if (data.redirect) {
                    window.location.href = data.redirect;
                    return;
                }
                if (!data.success) {
                    showActionAlert("error", data.message || "Unable to update bundle quantity");
                    return;
                }
                qtyEl.textContent = data.quantity;
                const lineEl = document.getElementById("cart-total-" + cartId);
                if (lineEl && typeof data.line_total !== "undefined") {
                    lineEl.textContent = formatRs(data.line_total);
                }
                const checkboxList = document.querySelectorAll(".js-cart-select");
                if (typeof data.line_total !== "undefined" && checkboxList.length) {
                    checkboxList.forEach(function (cb) {
                        if (cb.value === String(cartId)) {
                            cb.setAttribute("data-line-total", Number(data.line_total).toFixed(2));
                            cb.dispatchEvent(new Event("change"));
                        }
                    });
                }
                const subEl = document.getElementById("cart-subtotal");
                const grandEl = document.getElementById("cart-grand-total");
                if (subEl && typeof data.subtotal !== "undefined") subEl.textContent = formatRs(data.subtotal);
                if (grandEl && typeof data.subtotal !== "undefined") {
                    const shippingFee = Number(grandEl.getAttribute("data-shipping-fee") || "0");
                    grandEl.textContent = formatRs(Number(data.subtotal) + shippingFee);
                }
            })
            .catch(() => showActionAlert("error", "Request failed"));
    });
}

document.addEventListener("change", function (event) {
    const select = event.target.closest(".js-bundle-variant-cart");
    if (!select) return;
    const cartBundleId = String(select.getAttribute("data-cart-bundle-id") || "").trim();
    if (!cartBundleId) return;
    const variants = {};
    const rowMap = {};
    document.querySelectorAll('.js-bundle-variant-cart[data-cart-bundle-id="' + cartBundleId + '"]').forEach(function (el) {
        const pid = String(el.getAttribute("data-product-id") || "").trim();
        if (!pid) return;
        if (!rowMap[pid]) rowMap[pid] = { color: "", storage: "", generic: "" };
        const val = String(el.value || "").trim();
        if (el.classList.contains("js-bundle-color-cart")) {
            rowMap[pid].color = val;
        } else if (el.classList.contains("js-bundle-storage-cart")) {
            rowMap[pid].storage = val;
        } else {
            rowMap[pid].generic = val;
        }
    });
    Object.keys(rowMap).forEach(function (pid) {
        const row = rowMap[pid];
        const parts = [];
        if (row.color) parts.push("Color: " + row.color);
        if (row.storage) parts.push("Storage: " + row.storage);
        if (parts.length === 0 && row.generic) parts.push(row.generic);
        variants[pid] = parts.join(" | ");
    });
    const url = "product_action.php?action=update_bundle_variants&ajax=1&cart_bundle_id=" +
        encodeURIComponent(cartBundleId) +
        "&bundle_variants=" + encodeURIComponent(JSON.stringify(variants));
    fetch(url, { method: "GET", headers: { "X-Requested-With": "XMLHttpRequest" } })
        .then(res => res.json())
        .then(data => {
            if (!data || !data.success) {
                showActionAlert("error", (data && data.message) ? data.message : "Unable to update variants");
                return;
            }
            showActionAlert("success", data.message || "Variant updated");
        })
        .catch(() => showActionAlert("error", "Request failed"));
});

function rtelBuildSimpleVariantString(row) {
    const colorEl = row.querySelector(".js-cart-line-color, .js-wishlist-line-color");
    const storageEl = row.querySelector(".js-cart-line-storage, .js-wishlist-line-storage");
    const genericEl = row.querySelector(".js-cart-line-generic, .js-wishlist-line-generic");
    const c = colorEl ? String(colorEl.value || "").trim() : "";
    const s = storageEl ? String(storageEl.value || "").trim() : "";
    const g = genericEl ? String(genericEl.value || "").trim() : "";
    const parts = [];
    if (c) parts.push("Color: " + c);
    if (s) parts.push("Storage: " + s);
    if (parts.length === 0 && g) return g;
    return parts.join(" | ");
}

document.addEventListener("change", function (event) {
    const target = event.target.closest(".js-cart-line-variant, .js-wishlist-line-variant");
    if (!target) return;
    const row = target.closest(".js-cart-variant-row, .js-wishlist-variant-row");
    if (!row) return;
    const isWishlist = row.classList.contains("js-wishlist-variant-row");
    const idVal = String(row.getAttribute(isWishlist ? "data-wishlist-id" : "data-cart-id") || "").trim();
    if (!idVal) return;

    if (isWishlist) {
        const wb = document.querySelector('.js-wishlist-add-cart[data-wishlist-id="' + idVal + '"]');
        if (wb) wb.disabled = true;
    } else {
        const cb = document.querySelector('.js-cart-select[name="cart_ids[]"][value="' + idVal + '"]');
        if (cb && cb.getAttribute("data-variant-required") === "1") {
            cb.disabled = true;
            cb.setAttribute("data-variant-ok", "0");
            if (cb.checked) {
                cb.checked = false;
                cb.dispatchEvent(new Event("change"));
            }
        }
    }

    const feature = rtelBuildSimpleVariantString(row);
    const url = isWishlist
        ? "product_action.php?action=update_wishlist_feature&ajax=1&wishlist_id=" +
          encodeURIComponent(idVal) +
          "&selected_feature=" +
          encodeURIComponent(feature)
        : "product_action.php?action=update_cart_feature&ajax=1&cart_id=" +
          encodeURIComponent(idVal) +
          "&selected_feature=" +
          encodeURIComponent(feature);

    fetch(url, { method: "GET", headers: { "X-Requested-With": "XMLHttpRequest" } })
        .then(res => res.json())
        .then(data => {
            if (!data || !data.success) {
                showActionAlert("error", (data && data.message) ? data.message : "Unable to save variant");
                return;
            }
            showActionAlert("success", data.message || "Variant saved");
            if (isWishlist) {
                const wb = document.querySelector('.js-wishlist-add-cart[data-wishlist-id="' + idVal + '"]');
                if (wb) wb.disabled = false;
            } else {
                const cb = document.querySelector('.js-cart-select[name="cart_ids[]"][value="' + idVal + '"]');
                if (cb) {
                    cb.disabled = false;
                    cb.setAttribute("data-variant-ok", "1");
                }
            }
        })
        .catch(() => showActionAlert("error", "Request failed"));
});

document.addEventListener("click", function (event) {
    const btn = event.target.closest(".js-wishlist-add-cart");
    if (!btn || btn.disabled) return;
    event.preventDefault();
    const wid = String(btn.getAttribute("data-wishlist-id") || "").trim();
    if (!wid) return;
    fetch(
        "product_action.php?action=move_wishlist_to_cart&ajax=1&wishlist_id=" + encodeURIComponent(wid),
        { method: "GET", headers: { "X-Requested-With": "XMLHttpRequest" } }
    )
        .then(res => res.json())
        .then(data => {
            if (data && data.redirect) {
                window.location.href = data.redirect;
                return;
            }
            if (!data || !data.success) {
                showActionAlert("error", (data && data.message) ? data.message : "Unable to add to cart");
                return;
            }
            showActionAlert("success", data.message || "Added to cart");
            window.location.href = "cart.php";
        })
        .catch(() => showActionAlert("error", "Request failed"));
});

function renderFeedbackNotice(type, text) {
    const notice = document.getElementById("feedbackNotice");
    if (!notice) return;
    const isError = type === "error";
    const iconClass = isError ? "ion-ios-alert" : "ion-ios-checkmark-circle";
    const alertClass = isError ? "alert-danger" : "alert-success";
    notice.className = "alert " + alertClass + " mb-3 d-flex align-items-center";
    notice.innerHTML = "<span class='" + iconClass + " mr-2' aria-hidden='true'></span>" + text;
}

function initFeedbackForm() {
    const form = document.getElementById("feedbackForm");
    if (!form) return;
    form.addEventListener("submit", function (e) {
        e.preventDefault();
        const formData = new FormData(form);
        formData.append("ajax", "1");
        if (!formData.get("btnComment")) {
            formData.append("btnComment", "1");
        }

        fetch("save_comment.php", {
            method: "POST",
            headers: { "X-Requested-With": "XMLHttpRequest" },
            body: formData
        })
            .then(res => res.json())
            .then(data => {
                if (!data) return;
                renderFeedbackNotice(data.type || (data.success ? "success" : "error"), data.text || "Unable to submit feedback.");
                if (data.success) {
                    form.reset();
                }
            })
            .catch(() => renderFeedbackNotice("error", "Request failed while submitting feedback."));
    });
}