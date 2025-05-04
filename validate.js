document.addEventListener("DOMContentLoaded", function () {
  const form = document.getElementById("contactForm");

  form.addEventListener("submit", function (e) {
    e.preventDefault(); // デフォルトの送信を止める

    const name = document.getElementById("name").value.trim();
    const email = document.getElementById("email").value.trim();
    const message = document.getElementById("message").value.trim();

    // バリデーション
    if (!name || !email || !message) {
      alert("すべての項目を入力してください。");
      return;
    }

    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailPattern.test(email)) {
      alert("有効なメールアドレスを入力してください。");
      return;
    }

    // フォームデータ送信
    const formData = new FormData();
    formData.append("name", name);
    formData.append("email", email);
    formData.append("message", message);

    fetch("/index.php", {
      method: "POST",
      body: formData,
    })
      .then((res) => res.text())
      .then((text) => {
        console.log("サーバーの応答:", text);
        console.log(JSON.parse(text));
        // 必要に応じて DOM に反映させる
        // document.body.insertAdjacentHTML("beforeend", `<p>${text}</p>`);
      })
      .catch((err) => {
        console.error("通信エラー:", err);
      });
  });
});
