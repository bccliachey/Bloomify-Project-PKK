const modal = document.getElementById("productModal");
const closeButton = document.getElementById("closeModal");

const detailName = document.getElementById("detailName");
const detailType = document.getElementById("detailType");
const detailImage = document.getElementById("detailImage");
const detailDescription = document.getElementById("detailDescription");
const detailPrice = document.getElementById("detailPrice");
const totalPrice = document.getElementById("totalPrice");
const cartMessage = document.getElementById("cartMessage");

let basePrice = 0;

const flowerPrices = [15000, 18000, 22000];

function formatRupiah(number) {
    return "Rp" + number.toLocaleString("id-ID");
}

function updateTotal() {
    let flowerTotal = 0;

    document.querySelectorAll(".flower-row").forEach((row, index) => {
        const quantity = Number(
            row.querySelector(".quantity-value").textContent
        );

        flowerTotal += quantity * flowerPrices[index];
    });

    totalPrice.textContent = formatRupiah(basePrice + flowerTotal);
}

function openProduct(card) {
    detailName.textContent = card.dataset.name;
    detailType.textContent = card.dataset.type;
    detailDescription.textContent = card.dataset.description;
    detailImage.src = card.dataset.image;
    detailImage.alt = card.dataset.name;

    basePrice = Number(card.dataset.price);

    detailPrice.textContent = formatRupiah(basePrice);

    document.querySelectorAll(".quantity-value").forEach(value => {
        value.textContent = "0";
    });

    cartMessage.textContent = "";
    updateTotal();

    modal.classList.add("show");
}

document.querySelectorAll(".product-card").forEach(card => {
    card.addEventListener("click", event => {
        // Jangan membuka popup ketika tombol wishlist diklik.
        if (event.target.closest(".wishlist")) return;

        openProduct(card);
    });
});

closeButton.addEventListener("click", () => {
    modal.classList.remove("show");
});

modal.addEventListener("click", event => {
    if (event.target === modal) {
        modal.classList.remove("show");
    }
});

// Tombol tambah dan kurang jumlah batang.
document.querySelectorAll(".flower-row").forEach(row => {
    const minus = row.querySelector(".minus");
    const plus = row.querySelector(".plus");
    const value = row.querySelector(".quantity-value");

    plus.addEventListener("click", () => {
        value.textContent = Number(value.textContent) + 1;
        updateTotal();
    });

    minus.addEventListener("click", () => {
        value.textContent = Math.max(0, Number(value.textContent) - 1);
        updateTotal();
    });
});

// Validasi minimum 3 batang.
document.getElementById("addToCart").addEventListener("click", () => {
    let totalStems = 0;

    document.querySelectorAll(".quantity-value").forEach(value => {
        totalStems += Number(value.textContent);
    });

    if (totalStems < 3) {
        cartMessage.textContent = "Please choose at least 3 flower stems.";
        return;
    }

    cartMessage.textContent =
        "Your selection is ready. Cart functionality will be added next.";
});

// Tutup popup dengan tombol Escape.
document.addEventListener("keydown", event => {
    if (event.key === "Escape") {
        modal.classList.remove("show");
    }
});