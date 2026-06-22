jwplayer("player").setup({
    file: "your-video.m3u8",
    image: "thumbnail.jpg",

    autostart: false,
    controls: true,

    skin: {
        controlbar: {
            background: "#0b0b0f",
            icons: "#ffffff",
            iconsActive: "#e50914" // 🔥 Rias red
        },
        timeslider: {
            progress: "#e50914",
            rail: "#1a1a25"
        }
    }
});

const player = jwplayer("player");

player.on('complete', function () {
    const nextBtn = document.querySelector('.btn-next-ep');

    if (nextBtn) {
        nextBtn.click(); // simulate click
    } else {
        console.log("No next episode found");
    }
});

const introStart = 0;
const introEnd = 90;

let skipBtn = document.createElement("div");
skipBtn.innerText = "⏭ Skip Intro";
skipBtn.className = "skip-intro-btn";
document.body.appendChild(skipBtn);

skipBtn.style.display = "none";

player.on('time', function (e) {
    if (e.position >= introStart && e.position <= introEnd) {
        skipBtn.style.display = "block";
    } else {
        skipBtn.style.display = "none";
    }
});

skipBtn.onclick = function () {
    player.seek(introEnd);
};

document.getElementById("nextEp")?.addEventListener("click", () => {
    document.querySelector(".btn-next-ep")?.click();
});

document.getElementById("prevEp")?.addEventListener("click", () => {
    document.querySelector(".btn-prev-ep")?.click();
});