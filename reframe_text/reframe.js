
async function reframeArticle() {
  const article = {
    'title': document.getElementById('articleTitle').value,
    'text': document.getElementById('articleText').value
  };

  const outputTitle = document.getElementById('outputTitle');
  outputTitle.innerHTML = 'Working, please wait...';

  // Get password from page
  const pageUrl = new URL(location.href);
  const urlParams = new URLSearchParams(pageUrl.search);
  const wParam = urlParams.get('w');

  let serverUrl = 'ajaxReframe.php?function=reframeArticle';

  if (wParam) {
    serverUrl += `&w=${wParam}`;
  }

  const response = await fetchFromServer(serverUrl, article);

  if (response.error) {
    outputTitle.innerText = 'Error: ' + response.error;
    return;
  }

  outputTitle.innerHTML = response.data.title;
  document.getElementById('outputText').innerHTML = response.data.text;

  console.log(JSON.stringify(response));
}

async function fetchFromServer(url, data={}, parseJSON=true){
  const optionsFetch = {
    method: 'POST',
    body: JSON.stringify(data),
    headers: {'Content-Type': 'application/json', 'Cache': 'no-cache'},
    credentials: 'same-origin',
  };

  const response = await fetch(url, optionsFetch);
  const responseText = await response.text();
  if (! responseText) throw new Error('Internal error: No response from server');

  if (parseJSON) {
    try {
      return JSON.parse(responseText);
    } catch (e) {
      throw new Error('Response from server is not valid JSON. Response:<br><br>' + responseText.substring(0, 500));
    }
  } else {
    return responseText;
  }
}
