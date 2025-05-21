document.addEventListener("DOMContentLoaded", function () {
  const form = document.getElementById("accountForm");

  form.addEventListener("submit", function (e) {
    e.preventDefault(); // デフォルトの送信を止める

    const name = document.getElementById("name").value.trim();
    const file = document.getElementById("file").files[0];

    // バリデーション
    if (!name) {
      alert("お名前を入力してください。");
      return;
    }

    // フォームデータ送信
    const formData = new FormData();
    formData.append("name", name);
    if (file) {
      formData.append("file", file);
    }

    fetch("/test2/account_index.php", {
      method: "POST",
      body: formData,
    })
      .then((res) => res.text())
      .then((text) => {
        try {
          $(".submit-error").show();
          // エラー出力欄がなければ省略可能
          if (text == "送信内容を保存しました。ありがとうございました。") {
            $(".submit-error").text("登録しました");
          } else {
            $(".submit-error").text("エラーが発生しました");
          }
        } catch (e) {
          console.error("レスポンス解析失敗:", e);
        }
      })
      .catch((err) => {
        console.error("通信エラー:", err);
      });
  });
});
