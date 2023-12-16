// https://pr-manage-pub.a8.net/media/index

const apiInterval = 1; //秒
const partersListUrl = `https://pr-manage-pub.a8.net/api/media/program/partner?size=100&page=1`;

const partnersResponse = await (await fetch(partersListUrl)).json();
console.log('1st result:', partnersResponse);

let result = [];
for(const partner of partnersResponse.data.partner_program_info_response_list){
    console.log('%s, %s (%s)', partner.program_id, partner.advertiser_name, partner.url_count);
    if(partner.url_count==0){
        continue;
    }
    const partnerDetailUrl = `https://pr-manage-pub.a8.net/api/media/site-url/search/${partner.program_id}?url=&page=1&size=100&order=ASC`
    const detail = await (await fetch(partnerDetailUrl)).json();
    console.log(detail);
    await new Promise(s => setTimeout(s, apiInterval*1000));
    for( const p of detail.data.search_site_url_info_response_list){
        p.program_id = partner.program_id;
        p.advertiser_name=partner.advertiser_name;
        result.push(p);    
    }
}

result = result.sort((a,b)=>{
    if( a.update_at === b.update_at ){
        if( a.program_id > b.program_id ){
            return 1;
        }
    }else if( a.update_at > b.update_at ){
        return 1;
    }else{
        return -1;
    }
});
console.table(result);

const main = document.querySelector('main');
const div = document.createElement('div');
div.id = 'a8-adcheck-table';
main.appendChild(div);

let style = `<style> div#a8-adcheck-table{min-width:980px;} main#wrapper{width:90% !important;} td.old{color:red;}</style>`;
let table =`
<div class="searchTable">
<table><tr>
<th class="providerName">広告主名</th> <th>URL</th><th style="min-width:180px">最終更新日時</th></tr> `;

const judgeDate = new Date();
judgeDate.setHours(judgeDate.getHours() -1);

for(const p of result ){
    table += `<tr><td>${p.advertiser_name}<br><b>${p.program_id}</b></td><td>${p.url}</td>`;
    if(new Date(p.update_at) < judgeDate){
        table += `<td class="old">${p.update_at}</td>`;
    }else{
        table += `<td>${p.update_at}</td>`;
    }
    table += `</tr>`;
}
table += '</table></div>';

div.innerHTML = style + table;
