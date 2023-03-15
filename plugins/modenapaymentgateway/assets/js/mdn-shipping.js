function doWork() {
    'use strict';
    var a = console.log("boomboom-boom");

    var e = document.getElementById("userShippingSelectionChoice"),
    value = e.value,
    userShippingLocation = e.options[e.selectedIndex].text,
    terminalID = document.getElementById("userShippingSelectionChoice").value,
    uniqueID = Date.now();

    fetch("https://jsonplaceholder.typicode.com/posts", {
        method: "POST",
        body: JSON.stringify({
            requestID: uniqueID,
            terminalID: terminalID,
            userShippingLocation: userShippingLocation
        }),
        headers: {
            "Content-type": "application/json; charset=UTF-8"
        }
    })
        .then((response) => response.json())
        .then((json) => console.log(json));;
}

window.onload = doWork();