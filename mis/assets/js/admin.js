// ================= API =================
const API = {
    parseJsonSafe(raw) {
        const text = String(raw || "").trim();
        if (!text) return null;
        try {
            return JSON.parse(text);
        } catch (e) {
            // Some PHP endpoints may emit warnings/notices before/after JSON.
            // Try every JSON-like object block and return the first valid payload.
            const candidates = text.match(/\{[\s\S]*?\}/g) || [];
            for (let i = 0; i < candidates.length; i++) {
                const c = String(candidates[i] || "").trim();
                if (!c) continue;
                try {
                    const parsed = JSON.parse(c);
                    if (parsed && typeof parsed === "object") {
                        return parsed;
                    }
                } catch (e2) {
                    // keep trying next candidate
                }
            }
            // Final fallback: widest object range
            const first = text.indexOf("{");
            const last = text.lastIndexOf("}");
            if (first !== -1 && last !== -1 && last > first) {
                const sliced = text.slice(first, last + 1);
                try {
                    return JSON.parse(sliced);
                } catch (e3) {}
            }
            return null;
        }
    },
    async get(url) {
        try {
            const res = await fetch(url);
            const text = await res.text();
            const parsed = this.parseJsonSafe(text);
            if (parsed) return parsed;
            console.error("Invalid JSON response (GET):", url, text);
            Swal.fire("Error!", "Invalid server response", "error");
            return { status: "error", message: "Invalid server response" };
        } catch (err) {
            console.error(err);
            Swal.fire("Error!", "Server error", "error");
            return { status: "error", message: "Server error" };
        }
    },

    async post(url, data) {
        try {
            const res = await fetch(url, {
                method: "POST",
                body: data
            });
            const text = await res.text();
            const parsed = this.parseJsonSafe(text);
            if (parsed) return parsed;
            console.error("Invalid JSON response (POST):", url, text);
            Swal.fire("Error!", "Invalid server response", "error");
            return { status: "error", message: "Invalid server response" };
        } catch (err) {
            console.error(err);
            Swal.fire("Error!", "Server error", "error");
            return { status: "error", message: "Server error" };
        }
    }
};

function exportTableToPdf(title, headers, bodyRows, fileName) {
    if (!window.jspdf || !window.jspdf.jsPDF) {
        Swal.fire("Error!", "PDF library not loaded", "error");
        return;
    }
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ orientation: "landscape" });
    doc.text(title, 14, 15);
    doc.autoTable({
        startY: 22,
        head: [headers],
        body: bodyRows
    });
    doc.save(fileName);
}

function syncSelectionFromSet(tableBodySelector, selectedSet) {
    const checks = document.querySelectorAll(`${tableBodySelector} .row-check`);
    checks.forEach((c) => {
        c.checked = selectedSet.has(String(c.value));
    });
}

function bindRowSelection(tableBodySelector, selectedSet) {
    const tableBody = document.querySelector(tableBodySelector);
    if (!tableBody) return;
    tableBody.addEventListener("change", (e) => {
        const target = e.target;
        if (!target || !target.classList || !target.classList.contains("row-check")) return;
        const id = String(target.value);
        if (target.checked) selectedSet.add(id);
        else selectedSet.delete(id);
    });
}

function bindSelectAll(masterId, tableBodySelector, selectedSet) {
    const master = document.getElementById(masterId);
    if (!master) return;
    master.addEventListener("change", () => {
        const checks = document.querySelectorAll(`${tableBodySelector} .row-check`);
        checks.forEach((c) => {
            c.checked = master.checked;
            const id = String(c.value);
            if (master.checked) selectedSet.add(id);
            else selectedSet.delete(id);
        });
    });
}

function createSimpleSpecRow(inputName, placeholder, value = "") {
    const row = document.createElement("div");
    row.className = "d-flex align-items-center gap-2 mb-2";
    row.innerHTML = `<input type="text" class="form-control form-control-sm" name="${inputName}" placeholder="${placeholder}" value="${(value || "").replace(/"/g, '&quot;')}"><button type="button" class="btn btn-outline-danger btn-sm">-</button>`;
    row.querySelector("button").addEventListener("click", () => row.remove());
    return row;
}

function createFeatureSpecRow(name = "", value = "") {
    const row = document.createElement("div");
    row.className = "row g-2 mb-2";
    row.innerHTML = `<div class="col-md-4"><input type="text" class="form-control form-control-sm" name="feature_name[]" placeholder="Feature" value="${(name || "").replace(/"/g, '&quot;')}"></div><div class="col-md-7"><input type="text" class="form-control form-control-sm" name="feature_value[]" placeholder="Value" value="${(value || "").replace(/"/g, '&quot;')}"></div><div class="col-md-1"><button type="button" class="btn btn-outline-danger btn-sm w-100">-</button></div>`;
    row.querySelector("button").addEventListener("click", () => row.remove());
    return row;
}

function addFeatureSpecValueRow(wrapId) {
    const wrap = document.getElementById(wrapId);
    if (!wrap) return;
    const nameInputs = wrap.querySelectorAll('input[name="feature_name[]"]');
    const lastName = nameInputs.length ? String(nameInputs[nameInputs.length - 1].value || "").trim() : "";
    if (!lastName) {
        Swal.fire("Feature name required", "Please add a feature name first.", "info");
        return;
    }
    wrap.appendChild(createFeatureSpecRow(lastName, ""));
}

function setProductSpecRows(wrapId, inputName, placeholder, values) {
    const wrap = document.getElementById(wrapId);
    if (!wrap) return;
    wrap.innerHTML = "";
    const list = Array.isArray(values) ? values.filter(Boolean) : [];
    if (!list.length) {
        wrap.appendChild(createSimpleSpecRow(inputName, placeholder, ""));
        return;
    }
    list.forEach((v) => wrap.appendChild(createSimpleSpecRow(inputName, placeholder, v)));
}

function setProductFeatureRows(features) {
    const wrap = document.getElementById("productEditFeaturesWrap");
    if (!wrap) return;
    wrap.innerHTML = "";
    const list = Array.isArray(features) ? features.filter((f) => f && f.feature_name && f.feature_name !== "Color") : [];
    if (!list.length) {
        wrap.appendChild(createFeatureSpecRow("", ""));
        return;
    }
    list.forEach((f) => wrap.appendChild(createFeatureSpecRow(f.feature_name, f.feature_value)));
}

function ensureSelectHasCurrentOption(selectEl, value, label) {
    if (!selectEl) return;
    const val = String(value || "").trim();
    if (!val) return;
    const hasOption = Array.from(selectEl.options).some((opt) => String(opt.value) === val);
    if (!hasOption) {
        const opt = document.createElement("option");
        opt.value = val;
        opt.textContent = label && String(label).trim() !== "" ? String(label) : ("Current (" + val + ")");
        opt.selected = true;
        selectEl.appendChild(opt);
    }
}

function isPhoneLikeCategoryLabel(text) {
    const t = String(text || "").trim().toLowerCase();
    return /phone/.test(t) || /mobile/.test(t) || /flagship/.test(t);
}

function syncEditGsmButtonVisibility() {
    const categoryEl = document.getElementById("product_edit_category");
    const btn = document.getElementById("editGsmFetchBtn");
    if (!categoryEl || !btn) return;
    const label = String(categoryEl.options[categoryEl.selectedIndex]?.text || "");
    const visible = isPhoneLikeCategoryLabel(label);
    btn.classList.toggle("d-none", !visible);
}


// ================= BRAND MODULE =================
const Brand = {

    page: 1,
    selectedIds: new Set(),

    // 🔹 LOAD DATA
    async load(page = 1, search = '') {
        this.page = page;

        document.getElementById("brandTable").innerHTML =
            `<tr><td colspan="8" class="text-center">Loading...</td></tr>`;

        const res = await API.get(`crud/fetch_brands.php?page=${page}&search=${encodeURIComponent(search)}`);

        if (!res || res.status !== "success") {
            Swal.fire("Error!", "Failed to load data", "error");
            return;
        }

        document.getElementById("brandTable").innerHTML = res.table;
        document.getElementById("pagination").innerHTML = res.pagination;
        syncSelectionFromSet("#brandTable", this.selectedIds);
    },

    // 🔹 SEARCH
    search() {
        const val = document.getElementById("search").value.trim();
        this.load(1, val);
    },

    // 🔹 ADD
    async add(e) {
        e.preventDefault();

        const form = new FormData(document.getElementById("brandForm"));
        const res = await API.post("crud/add_brand.php", form);

        if (res && res.status === "success") {
            Swal.fire("Added!", "Brand added successfully", "success");
            document.getElementById("brandForm").reset();
            this.load();
        } else {
            Swal.fire("Error!", res?.message || "Insert failed", "error");
        }
    },

    // 🔹 DELETE
    delete(id) {
        Swal.fire({
            title: "Are you sure?",
            text: "This will be deleted!",
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#d33"
        }).then(async (result) => {

            if (result.isConfirmed) {

                const form = new FormData();
                form.append("id", id);

                const res = await API.post("crud/delete_brand.php", form);

                if (res && res.status === "success") {
                    Swal.fire("Deleted!", "", "success");
                    this.selectedIds.delete(String(id));
                    this.load(this.page);
                } else {
                    Swal.fire("Error!", "Delete failed", "error");
                }
            }
        });
    },

    // 🔹 STATUS TOGGLE
    async status(id) {
        const form = new FormData();
        form.append("id", id);

        const res = await API.post("crud/status_brand.php", form);

        if (res && res.status === "success") {
            this.load(this.page);
        } else {
            Swal.fire("Error!", "Status update failed", "error");
        }
    },

    // 🔹 EDIT (LOAD DATA INTO MODAL)
    async edit(id) {
        const res = await API.get(`crud/get_brand.php?id=${id}`);

        if (!res || res.status === "error") {
            Swal.fire("Error!", res?.message || "Brand not found", "error");
            return;
        }

        document.getElementById("edit_id").value = res.brand_id;
        document.getElementById("edit_name").value = res.name;
        document.getElementById("edit_desc").value = res.description;

        new bootstrap.Modal(document.getElementById("editModal")).show();
    },

    // 🔹 UPDATE
    async update(e) {
        e.preventDefault();

        const form = new FormData(document.getElementById("editForm"));
        const res = await API.post("crud/update_brand.php", form);

        if (res && res.status === "success") {
            Swal.fire("Updated!", "", "success");

            bootstrap.Modal
                .getInstance(document.getElementById("editModal"))
                .hide();

            this.load(this.page);
        } else {
            Swal.fire("Error!", "Update failed", "error");
        }
    }

};

const Category = {
    page: 1,
    selectedIds: new Set(),
    async load(page = 1, search = "") {
        this.page = page;
        document.getElementById("categoryTable").innerHTML = `<tr><td colspan="7" class="text-center">Loading...</td></tr>`;
        const res = await API.get(`crud/fetch_categories.php?page=${page}&search=${encodeURIComponent(search)}`);
        if (!res || res.status !== "success") {
            Swal.fire("Error!", "Failed to load categories", "error");
            return;
        }
        document.getElementById("categoryTable").innerHTML = res.table;
        document.getElementById("categoryPagination").innerHTML = res.pagination;
        syncSelectionFromSet("#categoryTable", this.selectedIds);
    },
    search() {
        this.load(1, document.getElementById("categorySearch").value.trim());
    },
    async add(e) {
        e.preventDefault();
        const form = new FormData(document.getElementById("categoryForm"));
        const res = await API.post("crud/add_category.php", form);
        if (res && res.status === "success") {
            Swal.fire("Added!", "Category added successfully", "success");
            document.getElementById("categoryForm").reset();
            this.load();
        } else {
            Swal.fire("Error!", res?.message || "Insert failed", "error");
        }
    },
    async edit(id) {
        const res = await API.get(`crud/get_category.php?id=${id}`);
        if (!res || res.status === "error") {
            Swal.fire("Error!", res?.message || "Category not found", "error");
            return;
        }
        document.getElementById("category_edit_id").value = res.cat_id;
        document.getElementById("category_edit_name").value = res.name;
        new bootstrap.Modal(document.getElementById("categoryEditModal")).show();
    },
    async update(e) {
        e.preventDefault();
        const form = new FormData(document.getElementById("categoryEditForm"));
        const res = await API.post("crud/update_category.php", form);
        if (res && res.status === "success") {
            Swal.fire("Updated!", "Category updated", "success");
            bootstrap.Modal.getInstance(document.getElementById("categoryEditModal")).hide();
            this.load(this.page);
        } else {
            Swal.fire("Error!", res?.message || "Update failed", "error");
        }
    },
    delete(id) {
        Swal.fire({
            title: "Are you sure?",
            text: "This category will be deleted",
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#d33"
        }).then(async (result) => {
            if (!result.isConfirmed) return;
            const form = new FormData();
            form.append("id", id);
            const res = await API.post("crud/delete_category.php", form);
            if (res && res.status === "success") {
                Swal.fire("Deleted!", "", "success");
                this.selectedIds.delete(String(id));
                this.load(this.page);
            } else {
                Swal.fire("Error!", res?.message || "Delete failed", "error");
            }
        });
    },
    async status(id) {
        const form = new FormData();
        form.append("id", id);
        const res = await API.post("crud/status_category.php", form);
        if (res && res.status === "success") this.load(this.page);
        else Swal.fire("Error!", "Status update failed", "error");
    }
};

const Product = {
    page: 1,
    selectedIds: new Set(),
    gsmPreviewPayload: null,
    gsmLastQuery: "",
    editExistingImages: [],
    editNewImageFiles: [],
    async load(page = 1, search = null, brandId = null, catId = null) {
        this.page = page;
        const searchInput = document.getElementById("productSearch");
        const brandFilterEl = document.getElementById("productFilterBrand");
        const categoryFilterEl = document.getElementById("productFilterCategory");
        if (search === null) search = searchInput ? searchInput.value.trim() : "";
        if (brandId === null) brandId = brandFilterEl ? brandFilterEl.value : "";
        if (catId === null) catId = categoryFilterEl ? categoryFilterEl.value : "";
        document.getElementById("productTable").innerHTML = `<tr><td colspan="8" class="text-center">Loading...</td></tr>`;
        const res = await API.get(`crud/fetch_products.php?page=${page}&search=${encodeURIComponent(search)}&brand_id=${encodeURIComponent(brandId || "")}&cat_id=${encodeURIComponent(catId || "")}`);
        if (!res || res.status !== "success") {
            Swal.fire("Error!", "Failed to load products", "error");
            return;
        }
        document.getElementById("productTable").innerHTML = res.table;
        document.getElementById("productPagination").innerHTML = res.pagination;
        syncSelectionFromSet("#productTable", this.selectedIds);
    },
    search() {
        this.load(1);
    },
    delete(id) {
        Swal.fire({
            title: "Are you sure?",
            text: "This product will be deleted",
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#d33"
        }).then(async (result) => {
            if (!result.isConfirmed) return;
            const form = new FormData();
            form.append("id", id);
            const res = await API.post("crud/delete_product.php", form);
            if (res && res.status === "success") {
                Swal.fire("Deleted!", "", "success");
                this.selectedIds.delete(String(id));
                this.load(this.page);
            } else {
                Swal.fire("Error!", res?.message || "Delete failed", "error");
            }
        });
    },
    async status(id) {
        const form = new FormData();
        form.append("id", id);
        const res = await API.post("crud/status_product.php", form);
        if (res && res.status === "success") this.load(this.page);
        else Swal.fire("Error!", "Status update failed", "error");
    },
    async edit(id) {
        const res = await API.get(`crud/get_product.php?id=${id}`);
        if (!res || res.status === "error") {
            Swal.fire("Error!", res?.message || "Product not found", "error");
            return;
        }
        resetProductEditTransientState();
        document.getElementById("product_edit_id").value = res.product_id;
        const catEl = document.getElementById("product_edit_category");
        if (catEl) {
            ensureSelectHasCurrentOption(catEl, res.cat_id, res.cat_name || "");
            catEl.value = String(res.cat_id || "");
        }
        const brandEl = document.getElementById("product_edit_brand");
        if (brandEl) {
            ensureSelectHasCurrentOption(brandEl, res.brand_id, res.brand_name || "");
            brandEl.value = String(res.brand_id || "");
        }
        document.getElementById("product_edit_name").value = res.name || "";
        document.getElementById("product_edit_modal").value = res.modal || "";
        document.getElementById("product_edit_description").value = res.description || "";
        document.getElementById("product_edit_price").value = res.price || 0;
        document.getElementById("product_edit_cprice").value = res.cprice || 0;
        document.getElementById("product_edit_quantity").value = res.quantity || 0;
        const specs = res.specs || {};
        setProductSpecRows("productEditColorsWrap", "colors[]", "Color", specs.colors || []);
        setProductSpecRows("productEditRamWrap", "ram_options[]", "RAM option", specs.ram_options || []);
        setProductSpecRows("productEditRomWrap", "rom_options[]", "ROM option", specs.rom_options || []);
        setProductFeatureRows(specs.features || []);
        Product.editExistingImages = Array.isArray(res.image_list) ? res.image_list.map((x) => String(x || "").trim()).filter(Boolean) : [];
        Product.editNewImageFiles = [];
        syncProductEditImageInput();
        renderProductEditImagePreviews();
        syncEditGsmButtonVisibility();
        new bootstrap.Modal(document.getElementById("productEditModal")).show();
    },
    async update(e) {
        e.preventDefault();
        const form = new FormData(document.getElementById("productEditForm"));
        form.set("keep_existing_images", JSON.stringify(Product.editExistingImages || []));
        const price = parseFloat(form.get("price") || "0");
        const cpriceRaw = (form.get("cprice") || "").toString().trim();
        const cprice = cpriceRaw === "" ? 0 : parseFloat(cpriceRaw || "0");
        if (price <= 0 || cprice < 0) {
            Swal.fire("Error!", "Price must be greater than 0 and compare price cannot be negative", "error");
            return;
        }
        const res = await API.post("crud/update_product.php", form);
        if (res && res.status === "success") {
            Swal.fire("Updated!", "Product updated", "success");
            bootstrap.Modal.getInstance(document.getElementById("productEditModal")).hide();
            this.load(this.page);
        } else {
            Swal.fire("Error!", res?.message || "Update failed", "error");
        }
    }
};

const Bundle = {
    page: 1,
    selectedIds: new Set(),
    async load(page = 1, search = "") {
        this.page = page;
        document.getElementById("bundleTable").innerHTML = `<tr><td colspan="10" class="text-center">Loading...</td></tr>`;
        const res = await API.get(`crud/fetch_bundles.php?page=${page}&search=${encodeURIComponent(search)}`);
        if (!res || res.status !== "success") {
            Swal.fire("Error!", "Failed to load bundles", "error");
            return;
        }
        document.getElementById("bundleTable").innerHTML = res.table;
        document.getElementById("bundlePagination").innerHTML = res.pagination;
        syncSelectionFromSet("#bundleTable", this.selectedIds);
    },
    search() {
        this.load(1, document.getElementById("bundleSearch").value.trim());
    },
    async add(e) {
        e.preventDefault();
        const form = new FormData(document.getElementById("bundleForm"));
        const selectedProducts = form.getAll("product_ids[]");
        if (selectedProducts.length < 2) {
            Swal.fire("Info", "Select at least 2 products", "info");
            return;
        }
        const res = await API.post("crud/add_bundle.php", form);
        if (res && res.status === "success") {
            Swal.fire("Added!", "Bundle added successfully", "success");
            document.getElementById("bundleForm").reset();
            const createProducts = document.getElementById("bundle_products_create");
            if (createProducts && createProducts.tomselect) {
                createProducts.tomselect.clear(true);
            }
            const createModel = document.getElementById("bundle_model_create");
            if (createModel && createModel.tomselect) {
                createModel.tomselect.clear(true);
            }
            this.load();
        } else {
            Swal.fire("Error!", res?.message || "Insert failed", "error");
        }
    },
    async edit(id) {
        const res = await API.get(`crud/get_bundle.php?id=${id}`);
        if (!res || res.status !== "success") {
            Swal.fire("Error!", res?.message || "Bundle not found", "error");
            return;
        }
        document.getElementById("bundle_edit_id").value = res.bundle_id;
        document.getElementById("bundle_edit_name").value = res.bundle_name || "";
        const oldImageEl = document.getElementById("bundle_edit_old_image");
        if (oldImageEl) oldImageEl.value = res.bundle_image || "";
        const modelEl = document.getElementById("bundle_edit_model");
        if (modelEl) {
            const modelVal = String(res.bundle_model || "");
            if (modelEl.tomselect) {
                if (modelVal && !modelEl.tomselect.options[modelVal]) {
                    modelEl.tomselect.addOption({ value: modelVal, text: modelVal });
                }
                modelEl.tomselect.setValue(modelVal, true);
            } else {
                modelEl.value = modelVal;
            }
        }
        document.getElementById("bundle_edit_price").value = res.bundle_price || 0;
        const expiryEl = document.getElementById("bundle_edit_expiry");
        if (expiryEl) expiryEl.value = res.expiry_date || "";
        const productSelect = document.getElementById("bundle_edit_products");
        const selectedIds = new Set(Array.isArray(res.product_ids) ? res.product_ids.map((x) => String(x)) : []);
        if (productSelect && productSelect.tomselect) {
            productSelect.tomselect.clear(true);
            productSelect.tomselect.setValue(Array.from(selectedIds), true);
        } else {
            Array.from(productSelect.options).forEach((opt) => {
                opt.selected = selectedIds.has(String(opt.value));
            });
        }
        new bootstrap.Modal(document.getElementById("bundleEditModal")).show();
    },
    async update(e) {
        e.preventDefault();
        const form = new FormData(document.getElementById("bundleEditForm"));
        const selectedProducts = form.getAll("product_ids[]");
        if (selectedProducts.length < 2) {
            Swal.fire("Info", "Select at least 2 products", "info");
            return;
        }
        const res = await API.post("crud/update_bundle.php", form);
        if (res && res.status === "success") {
            Swal.fire("Updated!", "", "success");
            bootstrap.Modal.getInstance(document.getElementById("bundleEditModal")).hide();
            this.load(this.page);
        } else {
            Swal.fire("Error!", res?.message || "Update failed", "error");
        }
    },
    delete(id) {
        Swal.fire({
            title: "Are you sure?",
            text: "This bundle will be deleted!",
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#d33"
        }).then(async (result) => {
            if (!result.isConfirmed) return;
            const form = new FormData();
            form.append("id", id);
            const res = await API.post("crud/delete_bundle.php", form);
            if (res && res.status === "success") {
                Swal.fire("Deleted!", "", "success");
                this.selectedIds.delete(String(id));
                this.load(this.page);
            } else {
                Swal.fire("Error!", res?.message || "Delete failed", "error");
            }
        });
    },
    async status(id) {
        const form = new FormData();
        form.append("id", id);
        const res = await API.post("crud/status_bundle.php", form);
        if (res && res.status === "success") {
            this.load(this.page);
        } else {
            Swal.fire("Error!", "Status update failed", "error");
        }
    }
};

function resetProductEditTransientState() {
    Product.gsmPreviewPayload = null;
    Product.gsmLastQuery = "";
    const form = document.getElementById("productEditForm");
    const imageInput = form ? form.querySelector('input[type="file"][name="images[]"]') : null;
    if (imageInput) {
        imageInput.value = "";
    }
    Product.editExistingImages = [];
    Product.editNewImageFiles = [];
    const keepEl = document.getElementById("product_edit_keep_existing_images");
    if (keepEl) keepEl.value = "[]";
    const imgWrap = document.getElementById("productEditImagesPreview");
    if (imgWrap) imgWrap.innerHTML = "";
    const previewBox = document.getElementById("editGsmPreview");
    const previewDesc = document.getElementById("editGsmPreviewDesc");
    const previewSpecs = document.getElementById("editGsmPreviewSpecs");
    if (previewBox) previewBox.classList.add("d-none");
    if (previewDesc) previewDesc.textContent = "";
    if (previewSpecs) previewSpecs.innerHTML = "";
}

function syncProductEditImageInput() {
    const input = document.getElementById("product_edit_images");
    if (!input) return;
    const dt = new DataTransfer();
    (Product.editNewImageFiles || []).forEach((f) => dt.items.add(f));
    input.files = dt.files;
    const keepEl = document.getElementById("product_edit_keep_existing_images");
    if (keepEl) keepEl.value = JSON.stringify(Product.editExistingImages || []);
}

function renderProductEditImagePreviews() {
    const wrap = document.getElementById("productEditImagesPreview");
    if (!wrap) return;
    wrap.innerHTML = "";

    (Product.editExistingImages || []).forEach((name, idx) => {
        const card = document.createElement("div");
        card.className = "image-preview-card";
        const img = document.createElement("img");
        img.src = "../images/" + encodeURIComponent(String(name));
        img.alt = "Existing image " + (idx + 1);
        const del = document.createElement("button");
        del.type = "button";
        del.className = "image-remove-btn";
        del.textContent = "×";
        del.title = "Remove image";
        del.addEventListener("click", function () {
            Product.editExistingImages.splice(idx, 1);
            syncProductEditImageInput();
            renderProductEditImagePreviews();
        });
        const cap = document.createElement("div");
        cap.className = "image-preview-caption";
        cap.textContent = String(name || "");
        card.appendChild(del);
        card.appendChild(img);
        card.appendChild(cap);
        wrap.appendChild(card);
    });

    (Product.editNewImageFiles || []).forEach((file, idx) => {
        const card = document.createElement("div");
        card.className = "image-preview-card";
        const img = document.createElement("img");
        img.alt = "New image " + (idx + 1);
        const del = document.createElement("button");
        del.type = "button";
        del.className = "image-remove-btn";
        del.textContent = "×";
        del.title = "Remove new image";
        del.addEventListener("click", function () {
            Product.editNewImageFiles.splice(idx, 1);
            syncProductEditImageInput();
            renderProductEditImagePreviews();
        });
        const cap = document.createElement("div");
        cap.className = "image-preview-caption";
        cap.textContent = String(file && file.name ? file.name : "New image");
        card.appendChild(del);
        card.appendChild(img);
        card.appendChild(cap);
        wrap.appendChild(card);
        const reader = new FileReader();
        reader.onload = function (e) {
            img.src = String((e && e.target && e.target.result) || "");
        };
        reader.readAsDataURL(file);
    });
}

async function fetchEditGsmPreview() {
    const nameEl = document.getElementById("product_edit_name");
    const modelEl = document.getElementById("product_edit_modal");
    const categoryEl = document.getElementById("product_edit_category");
    const name = String((nameEl && nameEl.value) || "").trim();
    const model = String((modelEl && modelEl.value) || "").trim();
    const categoryText = categoryEl ? String(categoryEl.options[categoryEl.selectedIndex]?.text || "").toLowerCase() : "";
    if (!name) {
        Swal.fire("Missing name", "Please enter product name first.", "info");
        return;
    }
    const looksPhone = /phone|mobile|smart/.test(categoryText) || /galaxy|iphone|redmi|pixel|oneplus|vivo|oppo|nokia|xiaomi|realme/.test((name + " " + model).toLowerCase());
    if (!looksPhone) {
        Swal.fire("Phone only", "This autofill is designed for phone products only.", "info");
        return;
    }
    const btn = document.getElementById("editGsmFetchBtn");
    if (btn) {
        btn.disabled = true;
        btn.textContent = "Fetching...";
    }
    try {
        const res = await fetch(`api/gsmarena_product_ai.php?name=${encodeURIComponent(name)}&model=${encodeURIComponent(model)}`);
        const data = await res.json();
        if (!data || data.status !== "success") {
            Swal.fire("Not found", data?.message || "Unable to fetch GSMArena data.", "warning");
            return;
        }
        Product.gsmPreviewPayload = data;
        const previewBox = document.getElementById("editGsmPreview");
        const previewDesc = document.getElementById("editGsmPreviewDesc");
        const previewSpecs = document.getElementById("editGsmPreviewSpecs");
        if (previewDesc) previewDesc.textContent = String(data.description || "").trim() || "No description found.";
        if (previewSpecs) {
            previewSpecs.innerHTML = "";
            (Array.isArray(data.specs) ? data.specs : []).forEach((s) => {
                const li = document.createElement("li");
                li.textContent = `${String(s.name || "").trim()}: ${String(s.value || "").trim()}`;
                previewSpecs.appendChild(li);
            });
        }
        if (previewBox) previewBox.classList.remove("d-none");
    } catch (err) {
        Swal.fire("Error", "Failed to fetch GSMArena data.", "error");
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.textContent = "AI Preview";
        }
    }
}

async function fetchEditGsmPreviewAuto() {
    const name = String((document.getElementById("product_edit_name")?.value) || "").trim();
    const model = String((document.getElementById("product_edit_modal")?.value) || "").trim();
    const queryKey = `${name}|||${model}`.toLowerCase();
    if (!name || Product.gsmLastQuery === queryKey) return;
    Product.gsmLastQuery = queryKey;
    await fetchEditGsmPreview();
}

function applyEditGsmPreview() {
    const data = Product.gsmPreviewPayload;
    if (!data) {
        Swal.fire("No preview", "Fetch GSMArena preview first.", "info");
        return;
    }
    const featureWrap = document.getElementById("productEditFeaturesWrap");
    if (featureWrap) featureWrap.innerHTML = "";
    (Array.isArray(data.specs) ? data.specs : []).forEach((s) => {
        const n = String(s.name || "").trim();
        const v = String(s.value || "").trim();
        if (!n || !v) return;
        featureWrap.appendChild(createFeatureSpecRow(n, v));
    });
    Swal.fire("Applied", "AI Preview specs applied. Add variants and any missing specs.", "success");
}

window.Brand = Brand;
window.Category = Category;
window.Product = Product;
window.Bundle = Bundle;


// ================= INIT =================
document.addEventListener("DOMContentLoaded", () => {

    if (document.getElementById("brandTable")) {

        // initial load
        Brand.load();

        // 🔹 search with debounce
        let timer;
        document.getElementById("search").addEventListener("keyup", () => {
            clearTimeout(timer);
            timer = setTimeout(() => Brand.search(), 300);
        });

        // 🔹 add form
        document.getElementById("brandForm")
            .addEventListener("submit", (e) => Brand.add(e));

        // 🔹 edit form
        document.getElementById("editForm")
            .addEventListener("submit", (e) => Brand.update(e));

        bindSelectAll("brandSelectAll", "#brandTable", Brand.selectedIds);
        bindRowSelection("#brandTable", Brand.selectedIds);
        const exportSelected = document.getElementById("brandExportSelected");
        const exportAll = document.getElementById("brandExportAll");
        if (exportSelected) {
            exportSelected.addEventListener("click", () => {
                if (!Brand.selectedIds.size) return Swal.fire("Info", "Select at least one row", "info");
                API.get(`crud/export_brands.php?ids=${encodeURIComponent(Array.from(Brand.selectedIds).join(","))}`).then((res) => {
                    if (!res || res.status !== "success" || !res.rows?.length) {
                        Swal.fire("Info", "No data to export", "info");
                        return;
                    }
                    const rows2 = res.rows.map((r, i) => [i + 1, r.name, r.description, r.status]);
                    exportTableToPdf("Brand List (Selected)", ["No", "Brand", "Description", "Status"], rows2, "brands-selected.pdf");
                });
            });
        }
        if (exportAll) {
            exportAll.addEventListener("click", () => {
                API.get("crud/export_brands.php").then((res) => {
                    if (!res || res.status !== "success" || !res.rows?.length) {
                        Swal.fire("Info", "No data to export", "info");
                        return;
                    }
                    const rows = res.rows.map((r, i) => [i + 1, r.name, r.description, r.status]);
                    exportTableToPdf("Brand List", ["No", "Brand", "Description", "Status"], rows, "brands-all.pdf");
                });
            });
        }
    }

    if (document.getElementById("categoryTable")) {
        Category.load();
        let timer;
        document.getElementById("categorySearch").addEventListener("keyup", () => {
            clearTimeout(timer);
            timer = setTimeout(() => Category.search(), 300);
        });
        document.getElementById("categoryForm").addEventListener("submit", (e) => Category.add(e));
        document.getElementById("categoryEditForm").addEventListener("submit", (e) => Category.update(e));
        bindSelectAll("categorySelectAll", "#categoryTable", Category.selectedIds);
        bindRowSelection("#categoryTable", Category.selectedIds);

        document.getElementById("categoryExportSelected").addEventListener("click", () => {
            if (!Category.selectedIds.size) return Swal.fire("Info", "Select at least one row", "info");
            API.get(`crud/export_categories.php?ids=${encodeURIComponent(Array.from(Category.selectedIds).join(","))}`).then((res) => {
                if (!res || res.status !== "success" || !res.rows?.length) {
                    Swal.fire("Info", "No data to export", "info");
                    return;
                }
                const rows = res.rows.map((r, i) => [i + 1, r.name, r.status]);
                exportTableToPdf("Category List (Selected)", ["No", "Category", "Status"], rows, "categories-selected.pdf");
            });
        });
        document.getElementById("categoryExportAll").addEventListener("click", () => {
            API.get("crud/export_categories.php").then((res) => {
                if (!res || res.status !== "success" || !res.rows?.length) {
                    Swal.fire("Info", "No data to export", "info");
                    return;
                }
                const rows = res.rows.map((r, i) => [i + 1, r.name, r.status]);
                exportTableToPdf("Category List", ["No", "Category", "Status"], rows, "categories-all.pdf");
            });
        });
    }

    if (document.getElementById("productTable")) {
        Product.load();
        let timer;
        document.getElementById("productSearch").addEventListener("keyup", () => {
            clearTimeout(timer);
            timer = setTimeout(() => Product.search(), 300);
        });
        const productFilterBrand = document.getElementById("productFilterBrand");
        const productFilterCategory = document.getElementById("productFilterCategory");
        if (productFilterBrand) {
            productFilterBrand.addEventListener("change", () => Product.load(1));
        }
        if (productFilterCategory) {
            productFilterCategory.addEventListener("change", () => Product.load(1));
        }
        bindSelectAll("productSelectAll", "#productTable", Product.selectedIds);
        bindRowSelection("#productTable", Product.selectedIds);
        document.getElementById("productEditForm").addEventListener("submit", (e) => Product.update(e));
        const addColorBtn = document.getElementById("addEditColorBtn");
        if (addColorBtn) addColorBtn.addEventListener("click", () => document.getElementById("productEditColorsWrap").appendChild(createSimpleSpecRow("colors[]", "Color")));
        const addRamBtn = document.getElementById("addEditRamBtn");
        if (addRamBtn) addRamBtn.addEventListener("click", () => document.getElementById("productEditRamWrap").appendChild(createSimpleSpecRow("ram_options[]", "RAM option")));
        const addRomBtn = document.getElementById("addEditRomBtn");
        if (addRomBtn) addRomBtn.addEventListener("click", () => document.getElementById("productEditRomWrap").appendChild(createSimpleSpecRow("rom_options[]", "ROM option")));
        const addFeatureBtn = document.getElementById("addEditFeatureBtn");
        if (addFeatureBtn) addFeatureBtn.addEventListener("click", () => document.getElementById("productEditFeaturesWrap").appendChild(createFeatureSpecRow("", "")));
        const addFeatureValueBtn = document.getElementById("addEditFeatureValueBtn");
        if (addFeatureValueBtn) addFeatureValueBtn.addEventListener("click", () => addFeatureSpecValueRow("productEditFeaturesWrap"));
        const editGsmFetchBtn = document.getElementById("editGsmFetchBtn");
        if (editGsmFetchBtn) editGsmFetchBtn.addEventListener("click", fetchEditGsmPreview);
        const editGsmApplyBtn = document.getElementById("editGsmApplyBtn");
        if (editGsmApplyBtn) editGsmApplyBtn.addEventListener("click", applyEditGsmPreview);
        const editCategoryEl = document.getElementById("product_edit_category");
        if (editCategoryEl) editCategoryEl.addEventListener("change", syncEditGsmButtonVisibility);
        const editNameEl = document.getElementById("product_edit_name");
        const editModelEl = document.getElementById("product_edit_modal");
        if (editNameEl) editNameEl.addEventListener("blur", fetchEditGsmPreviewAuto);
        if (editModelEl) editModelEl.addEventListener("blur", fetchEditGsmPreviewAuto);
        const productEditImagesInput = document.getElementById("product_edit_images");
        if (productEditImagesInput) {
            productEditImagesInput.addEventListener("change", () => {
                const incoming = Array.from(productEditImagesInput.files || []);
                const existingCount = (Product.editExistingImages || []).length;
                const maxAdd = Math.max(0, 5 - existingCount);
                if (maxAdd <= 0) {
                    Swal.fire("Limit reached", "Maximum 5 images are allowed. Remove an existing image first.", "info");
                    Product.editNewImageFiles = [];
                } else {
                    const dedupe = new Set((Product.editNewImageFiles || []).map((f) => [f.name, f.size, f.lastModified].join("|")));
                    incoming.forEach((f) => {
                        const key = [f.name, f.size, f.lastModified].join("|");
                        if (!dedupe.has(key)) {
                            Product.editNewImageFiles.push(f);
                            dedupe.add(key);
                        }
                    });
                    if (Product.editNewImageFiles.length > maxAdd) {
                        Product.editNewImageFiles = Product.editNewImageFiles.slice(0, maxAdd);
                        Swal.fire("Limit reached", "Only up to 5 total images are allowed.", "info");
                    }
                }
                syncProductEditImageInput();
                renderProductEditImagePreviews();
            });
        }
        const productEditModalEl = document.getElementById("productEditModal");
        if (productEditModalEl) {
            productEditModalEl.addEventListener("hidden.bs.modal", resetProductEditTransientState);
        }
        document.getElementById("productExportSelected").addEventListener("click", () => {
            if (!Product.selectedIds.size) return Swal.fire("Info", "Select at least one row", "info");
            API.get(`crud/export_products.php?ids=${encodeURIComponent(Array.from(Product.selectedIds).join(","))}`).then((res) => {
                if (!res || res.status !== "success" || !res.rows?.length) {
                    Swal.fire("Info", "No data to export", "info");
                    return;
                }
                const rows = res.rows.map((r, i) => [i + 1, r.name, r.quantity, r.price, r.status]);
                exportTableToPdf("Product List (Selected)", ["No", "Name", "Quantity", "Price", "Status"], rows, "products-selected.pdf");
            });
        });
        document.getElementById("productExportAll").addEventListener("click", () => {
            API.get("crud/export_products.php").then((res) => {
                if (!res || res.status !== "success" || !res.rows?.length) {
                    Swal.fire("Info", "No data to export", "info");
                    return;
                }
                const rows = res.rows.map((r, i) => [i + 1, r.name, r.quantity, r.price, r.status]);
                exportTableToPdf("Product List", ["No", "Name", "Quantity", "Price", "Status"], rows, "products-all.pdf");
            });
        });
    }

    if (document.getElementById("bundleTable")) {
        Bundle.load();
        let timer;
        document.getElementById("bundleSearch").addEventListener("keyup", () => {
            clearTimeout(timer);
            timer = setTimeout(() => Bundle.search(), 300);
        });
        document.getElementById("bundleForm").addEventListener("submit", (e) => Bundle.add(e));
        document.getElementById("bundleEditForm").addEventListener("submit", (e) => Bundle.update(e));
        bindSelectAll("bundleSelectAll", "#bundleTable", Bundle.selectedIds);
        bindRowSelection("#bundleTable", Bundle.selectedIds);
        document.getElementById("bundleExportSelected").addEventListener("click", () => {
            if (!Bundle.selectedIds.size) return Swal.fire("Info", "Select at least one row", "info");
            API.get(`crud/export_bundles.php?ids=${encodeURIComponent(Array.from(Bundle.selectedIds).join(","))}`).then((res) => {
                if (!res || res.status !== "success" || !res.rows?.length) {
                    Swal.fire("Info", "No data to export", "info");
                    return;
                }
                const rows = res.rows.map((r, i) => [i + 1, r.name, r.price, r.products, r.status]);
                exportTableToPdf("Bundle List (Selected)", ["No", "Bundle", "Modal", "Price", "Expiry", "Products", "Status"], res.rows.map((r, i) => [i + 1, r.name, r.model || "", r.price, r.expiry || "No expiry", r.products || "", r.status]), "bundles-selected.pdf");
            });
        });
        document.getElementById("bundleExportAll").addEventListener("click", () => {
            API.get("crud/export_bundles.php").then((res) => {
                if (!res || res.status !== "success" || !res.rows?.length) {
                    Swal.fire("Info", "No data to export", "info");
                    return;
                }
                const rows = res.rows.map((r, i) => [i + 1, r.name, r.model || "", r.price, r.expiry || "No expiry", r.products || "", r.status]);
                exportTableToPdf("Bundle List", ["No", "Bundle", "Modal", "Price", "Expiry", "Products", "Status"], rows, "bundles-all.pdf");
            });
        });
    }

});