
function CrashPartnerGraph(divID, data, optionsUser=[], filter=null) {
  let widthContainer = 800;
  let heightContainer = 600;

  let xLabel = '';
  let yLabel = '';

  const margin = {top: 75, left: 85, right: 10, bottom: 10};
  const iconWidth = 30;
  const fontSize = '12px';

  if (optionsUser) {
    if (optionsUser.width) widthContainer = optionsUser.width;
    if (optionsUser.height) heightContainer = optionsUser.height;
    if (optionsUser.xLabel) xLabel = optionsUser.xLabel;
    if (optionsUser.yLabel) yLabel = optionsUser.yLabel;
  }

  // Clear current chart
  d3.select('#' + divID).selectAll('*').remove();

  // Create new graph
  const svg = d3.select('#' + divID)
    .append('svg')
    .attr('id', 'svgGraph')
    // Make SVG responsive by preserving aspect ratio, adding viewbox and omitting width and height attributes
    .attr("preserveAspectRatio", "xMinYMin meet")
    .attr("viewBox", `0 0 ${widthContainer} ${heightContainer}`)

  const victimModes = [];
  const partnerModes = [];
  data.forEach(d => {if (!victimModes.includes(d.victimMode)) victimModes.push(d.victimMode)});
  data.forEach(d => {if (!partnerModes.includes(d.partnerMode)) partnerModes.push(d.partnerMode)});

  const victimTotals = [];
  const partnerTotals = [];
  victimModes.forEach(v => victimTotals[v] = 0);
  partnerModes.forEach(p => partnerTotals[p] = 0);

  data.forEach(d => {
    if (d.value) {
      victimTotals[d.victimMode] += d.value;
      partnerTotals[d.partnerMode] += d.value;
    }
  });

  const xScale = d3.scaleBand()
    .domain(partnerModes)
    .range([margin.left, widthContainer - margin.right]);

  const yScale = d3.scaleBand()
    .domain(victimModes.reverse())
    .range([heightContainer - margin.bottom, margin.top]);

  // Radius scale is a square root scale, because value = area πr2
  const dataValueExtent = d3.extent(data, d => d.value);
  const rScaleData = d3.scaleSqrt()
    .domain(dataValueExtent)
    .range([1, yScale.bandwidth()/2]);

  // x-axis
  svg.append('g')
    .attr('class', 'x-axis')
    .style('font-size', fontSize)
    .attr('transform', `translate(0, ${margin.top - 30})`)
    .call(d3.axisTop(xScale).tickSize(0))
    .select('.domain').remove();

  // y-axis
  svg.append('g')
    .attr('class', 'y-axis')
    .style('font-size', fontSize)
    .attr('transform', `translate(${margin.left - 15}, 0)`)
    .call(d3.axisLeft(yScale).tickSize(0))
    .select('.domain').remove();

  // x-axis
  // Remove mode integer texts. We replace these with icons
  svg.select('.x-axis').selectAll('text').remove();

  // x-axis
  const x_tick = svg.selectAll('.x-axis .tick').data(partnerModes);

  // x-axis icons
  x_tick
    .append('image')
    .attr('xlink:href', d => getTransportationModeIcon(parseInt(d)))
    .attr('x', (-iconWidth / 2) + 'px')
    .attr('y', (-iconWidth + 5) + 'px')
    .attr('width', iconWidth)
    .attr('height', iconWidth)
    .style('opacity', 0.6);

  // x-axis total texts
  x_tick
    .append("text")
    .attr('dx', 0)
    .attr('dy', '15px')
    .style('font-size', fontSize)
    .style('fill', '#ffffff')
    .text(d => d3.format('.3~s')(partnerTotals[parseInt(d)]));

  // y-axis
  // Remove integer mode texts. We replace these with icons
  svg.select('.y-axis').selectAll('text').remove();

  const y_tick = svg.selectAll('.y-axis .tick').data(victimModes);

  // y-axis image ticks
  y_tick
    .append('image')
    .attr('xlink:href', d => getTransportationModeIcon(parseInt(d)))
    .attr('x', (-iconWidth / 2 - 35) + 'px')
    .attr('y', (-iconWidth / 2) + 'px')
    .attr('width', iconWidth)
    .attr('height',iconWidth)
    .style('opacity', 0.6);

  // y-axis total texts
  y_tick
    .append("text")
    .attr('dx', '8px')
    .attr('dy', '3px')
    .style('font-size', fontSize)
    .style('fill', '#ffffff')
    .text(d => d3.format('.3~s')(victimTotals[parseInt(d)]));

  if (xLabel) {
    svg.append('text')
      .attr('transform', `translate(${widthContainer / 2}, 30)`)
      .attr('dy', '-1em')
      .style('text-anchor', 'middle')
      .style('fill', '#fff')
      .text(xLabel);
  }

  if (yLabel) {
    svg.append('text')
      .attr('transform', 'rotate(-90)')
      .attr('x', - heightContainer / 2)
      .attr('y', 0)
      .attr('dy', '1em')
      .style('text-anchor', 'middle')
      .style('fill', '#fff')
      .text(yLabel);
  }

  // create the tooltip element
  const tooltip = d3.select('#' + divID)
    .append('div')
    .attr('id', 'tooltip' + divID)
    .style('display', 'none')
    .style('position', 'absolute')
    .style('color', 'black')
    .style('background-color', 'white')
    .style('font-size', fontSize)
    .style('border', 'solid 1px #000')
    .style('border-radius', '5px')
    .style('padding', '2px 5px');

  function getTransportationModeIcon(transportationModeValue){
    return transportationModeValue === -1? '/images/unilateral_white.svg' : '/images/transportation_modes/' + transportationImageFileName(transportationModeValue, true);
  }

  const mouseoverItem = (event, data) => {
    const modeText = data.partnerMode === -1? translate('One-sided_crash') : translate('Counterparty') + ':&nbsp;' + transportationModeText(data.partnerMode);

    let html = '';
    html += `${transportationModeText(data.victimMode)}<br>`;
    html += `${d3.format('.3~s')(data.value)}&nbsp`;
    html += data.value === 1? translate('dead_(single)') : translate('dead_(multiple)');
    if (filter.healthInjured) html += '/' + translate('injured');
    html += '<br>' + modeText;

    tooltip.html(html).style('display', 'flex');

    d3.select(event.currentTarget)
      .style('cursor', data => data.value === 0? 'default' : 'pointer')
      .select('.data-circle')
      .transition()
      .duration(200)
      .style('fill-opacity', 1)
      .style('fill', '#f23825')
      .attr('r', yScale.bandwidth());

    d3.select(event.currentTarget)
      .select('.data-text')
      .text(d => d3.format('.3~s')(d.value))
      .transition()
      .duration(200)
      .style('font-size', '20px');
  };

  const mousemoveItem = (event, data) => {
    const [pointer_x, pointer_y] = d3.pointer(event);
    const svgWidth = d3.select('#' + divID).node().getBoundingClientRect().width;
    const scaleFactor = svgWidth / widthContainer;

    let xMouse = xScale(data.partnerMode) + pointer_x;
    let yMouse = yScale(data.victimMode) + pointer_y;

    xMouse = xMouse * scaleFactor + 25;
    yMouse = yMouse * scaleFactor - 25;

    tooltip
      .style('left', xMouse + 'px')
      .style('top', yMouse + 'px');
  };

  const mouseleaveItem = (event, data) => {
    tooltip.style('display', 'none');

    d3.select(event.currentTarget)
      .style('cursor', 'default')
      .select('.data-circle')
      .transition()
      .duration(200)
      .style('fill-opacity', d => d.value > 0? 0.8 : 0.5)
      .style('fill', d => d.value > 0? '#f23825' : '#fff')
      .attr('r', data => data.value > 0? rScaleData(data.value) : 1);

    d3.select(event.currentTarget)
      .select('.data-text')
      .transition()
      .duration(200)
      .text(d => d.value > 0? d3.format('.3~s')(d.value) : '')
      .style('font-size', fontSize);
  };

  // Add data background
  svg.append('rect')
    .attr('width', widthContainer - margin.left - margin.right)
    .attr('height', heightContainer - margin.top - margin.bottom)
    .attr('x', margin.left)
    .attr('y', margin.top)
    .attr('fill', '#000')
    .attr('fill-opacity', 0.1);

  // Data item
  const dataElement = svg.selectAll()
    .data(data)
    .enter()
    .append("a")
    .attr("xlink:href", d => {
      let url = '/?search=';
      if (filter.text) url += encodeURIComponent(filter.text);
      url += '&persons=' + d.victimMode + 'd';

      if (filter.healthInjured) url += 'i';
      if (d.victimMode === d.partnerMode) url += 'r'; // Restricted
      else if (d.partnerMode === -1) url += 'u'; // Unilateral
      else url += `,${d.partnerMode}`;

      if (filter.child) url += '&child=1';
      if (filter.country) url += '&country=' + filter.country;
      if (filter.period) {
        url += '&period=' + filter.period;
        if (filter.dateFrom) url += '&date_from=' + filter.dateFrom;
        if (filter.dateTo) url += '&date_to=' + filter.dateTo;
      }
      return url;})
    .style('text-decoration', 'none')
    .append("g")
    .attr("transform", d => `translate(${xScale(d.partnerMode) + xScale.bandwidth()/2}, ${yScale(d.victimMode) + yScale.bandwidth()/2})`)
    .on('mouseover', mouseoverItem)
    .on('mousemove', mousemoveItem)
    .on('mouseleave', mouseleaveItem);

  // Add empty background to equalize a link sizes
  dataElement.append('circle')
   .attr('r', yScale.bandwidth()/2)
   .attr('fill-opacity', 0);

  dataElement.append('circle')
    .attr('class', 'data-circle')
    .attr('r', d => d.value > 0? rScaleData(d.value) : 1)
    .style('fill-opacity', d => d.value > 0? 0.8 : 0.5)
    .style('fill', d => d.value > 0? '#f23825' : '#fff');

  // Data text
  dataElement
    .append("text")
    .attr('class', 'data-text')
    .attr('text-anchor', 'middle')
    .attr('alignment-baseline', 'central')
    .attr('dominant-baseline', 'middle')
    .style('font-size', fontSize)
    .style('fill', '#ffffff')
    .text(d => d.value > 0? d3.format('.3~s')(d.value) : '');

}
