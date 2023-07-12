/* empty css             */import{g as K,r as Y}from"./params.597cd0f5.js";import{N as W}from"./WpTable.4d19dc46.js";import"./default-i18n.ab92175e.js";import"./constants.7044c894.js";import{_ as k,o as n,c,f as s,r as d,h as p,a as u,d as l,g as y,F as w,i as S,t as r,n as P,w as _,e as v,k as q,x as H,T as E}from"./_plugin-vue_export-helper.2d9794a3.js";import"./index.02a5ed9a.js";import{S as X}from"./SaveChanges.bc66cd69.js";import{d as U,a as D,m as B,b as V}from"./vuex.esm-bundler.8589b2dd.js";import{a as J,C as Q,G as Z}from"./Header.33faf032.js";import{S as R,d as x,B as tt,a as z,b as G}from"./Caret.42a820e0.js";import{C as et,a as st}from"./LicenseKeyBar.89175103.js";import{S as it}from"./Logo.81e1a7f3.js";import{S as ot}from"./Support.7b58db1c.js";import{C as nt}from"./Tabs.eb56046b.js";import{D as at}from"./Date.47e384e5.js";import{S as rt}from"./Exclamation.9b2c9d16.js";import{U as ct}from"./Url.c71d5763.js";import{S as lt}from"./Gear.b05c5b07.js";import{T as I}from"./Slide.cd756e61.js";const dt={},ut={viewBox:"0 0 24 24",fill:"none",xmlns:"http://www.w3.org/2000/svg",class:"aioseo-description"},ft=s("path",{d:"M0 0h24v24H0V0z",fill:"none"},null,-1),ht=s("path",{"fill-rule":"evenodd","clip-rule":"evenodd",d:"M8 16h8v2H8zm0-4h8v2H8zm6-10H6c-1.1 0-2 .9-2 2v16c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm4 18H6V4h7v5h5v11z",fill:"currentColor"},null,-1),_t=[ft,ht];function mt(t,e){return n(),c("svg",ut,_t)}const pt=k(dt,[["render",mt]]),gt={},vt={viewBox:"0 0 24 24",fill:"none",xmlns:"http://www.w3.org/2000/svg",class:"aioseo-folder-open"},yt=s("path",{d:"M0 0h24v24H0V0z",fill:"none"},null,-1),bt=s("path",{"fill-rule":"evenodd","clip-rule":"evenodd",d:"M20 6h-8l-2-2H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2zm0 12H4V8h16v10z",fill:"currentColor"},null,-1),kt=[yt,bt];function $t(t,e){return n(),c("svg",vt,kt)}const Nt=k(gt,[["render",$t]]);const wt={components:{CoreApiBar:et,CoreLicenseKeyBar:st,CoreUpgradeBar:J,SvgAioseoLogo:it,SvgClose:R,SvgDescription:pt,SvgFolderOpen:Nt,SvgSupport:ot},data(){return{searchItem:null,strings:{close:this.$t.__("Close",this.$td),search:this.$t.__("Search",this.$td),viewAll:this.$t.__("View All",this.$td),docs:this.$t.__("Docs",this.$td),viewDocumentation:this.$t.__("View Documentation",this.$td),browseDocumentation:this.$t.sprintf(this.$t.__("Browse documentation, reference material, and tutorials for %1$s.",this.$td),"AIOSEO"),viewAllDocumentation:this.$t.__("View All Documentation",this.$td),getSupport:this.$t.__("Get Support",this.$td),submitTicket:this.$t.__("Submit a ticket and our world class support team will be in touch soon.",this.$td),submitSupportTicket:this.$t.__("Submit a Support Ticket",this.$td),upgradeToPro:this.$t.__("Upgrade to Pro",this.$td)}}},computed:{...U(["settings","isUnlicensed"]),...D(["showHelpModal","helpPanel","pong"]),filteredDocs(){return this.searchItem!==""?Object.values(this.helpPanel.docs).filter(t=>this.searchItem!==null?t.title.toLowerCase().includes(this.searchItem.toLowerCase()):null):null}},methods:{inputSearch:function(t){x(()=>{this.searchItem=t},1e3)},toggleSection:function(t){t.target.parentNode.parentNode.classList.toggle("opened")},toggleDocs:function(t){t.target.previousSibling.classList.toggle("opened"),t.target.style.display="none"},toggleModal(){document.getElementById("aioseo-help-modal").classList.toggle("visible"),document.body.classList.toggle("modal-open")},getCategoryDocs(t){return Object.values(this.helpPanel.docs).filter(e=>e.categories.flat().includes(t)?e:null)}}},Ct={id:"aioseo-help-modal",class:"aioseo-help"},St={class:"aioseo-help-header"},Dt={class:"logo"},Lt=["href"],At=["title"],Tt={class:"help-content"},Pt={id:"aioseo-help-search"},Bt={id:"aioseo-help-result"},It={class:"aioseo-help-docs"},Mt={class:"icon"},Ot=["href"],Ht={id:"aioseo-help-categories"},Et={class:"aioseo-help-categories-toggle"},Ut={class:"folder-open"},zt={class:"title"},qt=s("span",{class:"dashicons dashicons-arrow-right-alt2"},null,-1),Vt={class:"aioseo-help-docs"},Rt={class:"icon"},Gt=["href"],jt={class:"aioseo-help-additional-docs"},Ft={class:"icon"},Kt=["href"],Yt={id:"aioseo-help-footer"},Wt={class:"aioseo-help-footer-block"},Xt=["href"],Jt={class:"aioseo-help-footer-block"},Qt=["href"];function Zt(t,e,a,b,i,o){const h=d("core-upgrade-bar"),m=d("core-license-key-bar"),f=d("core-api-bar"),g=d("svg-aioseo-logo"),L=d("svg-close"),M=d("base-input"),A=d("svg-description"),j=d("svg-folder-open"),T=d("base-button"),F=d("svg-support");return n(),c("div",Ct,[!t.$isPro&&t.settings.showUpgradeBar&&t.pong?(n(),p(h,{key:0})):u("",!0),t.$isPro&&t.isUnlicensed&&t.pong?(n(),p(m,{key:1})):u("",!0),t.pong?u("",!0):(n(),p(f,{key:2})),s("div",St,[s("div",Dt,[t.isUnlicensed?(n(),c("a",{key:0,href:t.$links.utmUrl("header-logo"),target:"_blank"},[l(g,{id:"aioseo-help-logo"})],8,Lt)):u("",!0),t.isUnlicensed?u("",!0):(n(),p(g,{key:1,id:"aioseo-help-logo"}))]),s("div",{id:"aioseo-help-close",title:i.strings.close,onClick:e[0]||(e[0]=y((...$)=>o.toggleModal&&o.toggleModal(...$),["stop"]))},[l(L)],8,At)]),s("div",Tt,[s("div",Pt,[l(M,{type:"text",size:"medium",placeholder:i.strings.search,"onUpdate:modelValue":e[1]||(e[1]=$=>o.inputSearch($))},null,8,["placeholder"])]),s("div",Bt,[s("ul",It,[(n(!0),c(w,null,S(o.filteredDocs,($,C)=>(n(),c("li",{key:C},[s("span",Mt,[l(A)]),s("a",{href:t.$links.utmUrl("help-panel-doc","",$.url),rel:"noopener noreferrer",target:"_blank"},r($.title),9,Ot)]))),128))])]),s("div",Ht,[s("ul",Et,[(n(!0),c(w,null,S(t.helpPanel.categories,($,C)=>(n(),c("li",{key:C,class:P(["aioseo-help-category",{opened:C==="getting-started"}])},[s("header",{onClick:e[2]||(e[2]=y(N=>o.toggleSection(N),["stop"]))},[s("span",Ut,[l(j)]),s("span",zt,r($),1),qt]),s("ul",Vt,[(n(!0),c(w,null,S(o.getCategoryDocs(C).slice(0,5),(N,O)=>(n(),c("li",{key:O},[s("span",Rt,[l(A)]),s("a",{href:t.$links.utmUrl("help-panel-doc","",N.url),rel:"noopener noreferrer",target:"_blank"},r(N.title),9,Gt)]))),128)),s("div",jt,[(n(!0),c(w,null,S(o.getCategoryDocs(C).slice(5,o.getCategoryDocs(C).length),(N,O)=>(n(),c("li",{key:O},[s("span",Ft,[l(A)]),s("a",{href:t.$links.utmUrl("help-panel-doc","",N.url),rel:"noopener noreferrer",target:"_blank"},r(N.title),9,Kt)]))),128))]),o.getCategoryDocs(C).length>=5?(n(),p(T,{key:0,class:"aioseo-help-docs-viewall gray medium",onClick:e[3]||(e[3]=y(N=>o.toggleDocs(N),["stop"]))},{default:_(()=>[v(r(i.strings.viewAll)+" "+r($)+" "+r(i.strings.docs),1)]),_:2},1024)):u("",!0)])],2))),128))])]),s("div",Yt,[s("div",Wt,[s("a",{href:t.$links.utmUrl("help-panel-all-docs","","https://aioseo.com/docs/"),rel:"noopener noreferrer",target:"_blank"},[l(A),s("h3",null,r(i.strings.viewDocumentation),1),s("p",null,r(i.strings.browseDocumentation),1),l(T,{class:"aioseo-help-docs-viewall gray small"},{default:_(()=>[v(r(i.strings.viewAllDocumentation),1)]),_:1})],8,Xt)]),s("div",Jt,[s("a",{href:!t.$isPro||!t.$aioseo.license.isActive?t.$links.getUpsellUrl("help-panel","get-support","liteUpgrade"):"https://aioseo.com/account/support/",rel:"noopener noreferrer",target:"_blank"},[l(F),s("h3",null,r(i.strings.getSupport),1),s("p",null,r(i.strings.submitTicket),1),t.$isPro&&t.$aioseo.license.isActive?(n(),p(T,{key:0,class:"aioseo-help-docs-support blue small"},{default:_(()=>[v(r(i.strings.submitSupportTicket),1)]),_:1})):u("",!0),!t.$isPro||!t.$aioseo.license.isActive?(n(),p(T,{key:1,class:"aioseo-help-docs-support green small"},{default:_(()=>[v(r(i.strings.upgradeToPro),1)]),_:1})):u("",!0)],8,Qt)])])])])}const xt=k(wt,[["render",Zt]]),te=""+window.__aioseoDynamicImportPreload__("images/dannie-detective.f19b97eb.png");const ee={emits:["dismiss-notification"],components:{BaseButton:tt,SvgCircleCheck:z,SvgCircleClose:G,SvgCircleExclamation:rt,SvgGear:lt,TransitionSlide:I},mixins:[ct,at],props:{notification:{type:Object,required:!0}},data(){return{active:!0,strings:{dismiss:this.$t.__("Dismiss",this.$td)}}},computed:{getIcon(){switch(this.notification.type){case"warning":return"svg-circle-exclamation";case"error":return"svg-circle-close";case"info":return"svg-gear";case"success":default:return"svg-circle-check"}},getDate(){return this.dateSqlToLocalRelative(this.notification.start)}},methods:{...B(["dismissNotifications","processButtonAction"]),processDismissNotification(){this.active=!1,this.dismissNotifications([this.notification.slug]),this.$emit("dismissed-notification")}}},se={class:"icon"},ie={class:"body"},oe={class:"title"},ne={class:"date"},ae=["innerHTML"],re={class:"actions"};function ce(t,e,a,b,i,o){const h=d("base-button"),m=d("transition-slide");return n(),p(m,{class:"aioseo-notification",active:i.active},{default:_(()=>[s("div",null,[s("div",se,[(n(),p(q(o.getIcon),{class:P(a.notification.type)},null,8,["class"]))]),s("div",ie,[s("div",oe,[s("div",null,r(a.notification.title),1),s("div",ne,r(o.getDate),1)]),s("div",{class:"notification-content",innerHTML:a.notification.content},null,8,ae),s("div",re,[a.notification.button1_label&&a.notification.button1_action?(n(),p(h,{key:0,size:"small",type:"gray",tag:t.getTagType(a.notification.button1_action),href:t.getHref(a.notification.button1_action),target:t.getTarget(a.notification.button1_action),onClick:e[0]||(e[0]=f=>t.processButtonClick(a.notification.button1_action,1)),loading:t.button1Loading},{default:_(()=>[v(r(a.notification.button1_label),1)]),_:1},8,["tag","href","target","loading"])):u("",!0),a.notification.button2_label&&a.notification.button2_action?(n(),p(h,{key:1,size:"small",type:"gray",tag:t.getTagType(a.notification.button2_action),href:t.getHref(a.notification.button2_action),target:t.getTarget(a.notification.button2_action),onClick:e[1]||(e[1]=f=>t.processButtonClick(a.notification.button2_action,2)),loading:t.button2Loading},{default:_(()=>[v(r(a.notification.button2_label),1)]),_:1},8,["tag","href","target","loading"])):u("",!0),a.notification.dismissed?u("",!0):(n(),c("a",{key:2,href:"#",class:"dismiss",onClick:e[2]||(e[2]=y((...f)=>o.processDismissNotification&&o.processDismissNotification(...f),["stop","prevent"]))},r(i.strings.dismiss),1))])])])]),_:1},8,["active"])}const le=k(ee,[["render",ce]]);const de={emits:["dismissed-notification"],components:{SvgCircleCheck:z,TransitionSlide:I},props:{notification:{type:Object,required:!0}},data(){return{step:1,active:!0,strings:{dismiss:this.$t.__("Dismiss",this.$td),yesILoveIt:this.$t.__("Yes, I love it!",this.$td),notReally:this.$t.__("Not Really...",this.$td),okYouDeserveIt:this.$t.__("Ok, you deserve it",this.$td),nopeMaybeLater:this.$t.__("Nope, maybe later",this.$td),giveFeedback:this.$t.__("Give feedback",this.$td),noThanks:this.$t.__("No thanks",this.$td)}}},computed:{...D(["options"]),...U(["licenseKey"]),title(){switch(this.step){case 2:return this.$t.__("That's Awesome!",this.$td);case 3:return this.$t.__("Help us improve",this.$td);default:return this.$t.sprintf(this.$t.__("Are you enjoying %1$s?",this.$td),"AIOSEO")}},content(){switch(this.step){case 2:return this.$t.__("Could you please do me a BIG favor and give it a 5-star rating on WordPress to help us spread the word and boost our motivation?",this.$td)+"<br><br><strong>~ Syed Balkhi<br>"+this.$t.sprintf(this.$t.__("CEO of %1$s",this.$td),"All in One SEO")+"</strong>";case 3:return this.$t.sprintf(this.$t.__("We're sorry to hear you aren't enjoying %1$s. We would love a chance to improve. Could you take a minute and let us know what we can do better?",this.$td),"All in One SEO");default:return""}},feedbackUrl(){const t=this.options.general&&this.licenseKey?this.licenseKey:"",e=this.$isPro?"pro":"lite";return this.$links.utmUrl("notification-review-notice",this.$aioseo.version,"https://aioseo.com/plugin-feedback/?wpf7528_24="+encodeURIComponent(this.$aioseo.urls.home)+"&wpf7528_26="+t+"&wpf7528_27="+e+"&wpf7528_28="+this.$aioseo.version)}},methods:{...B(["dismissNotifications","processButtonAction"]),processDismissNotification(t=!1){this.active=!1,this.dismissNotifications([this.notification.slug+(t?"-delay":"")]),this.$emit("dismissed-notification")}}},ue={class:"icon"},fe={class:"body"},he={class:"title"},_e=["innerHTML"],me={class:"actions"};function pe(t,e,a,b,i,o){const h=d("svg-circle-check"),m=d("base-button"),f=d("transition-slide");return n(),p(f,{class:"aioseo-notification",active:i.active},{default:_(()=>[s("div",null,[s("div",ue,[l(h,{class:"success"})]),s("div",fe,[s("div",he,[s("div",null,r(o.title),1)]),s("div",{class:"notification-content",innerHTML:o.content},null,8,_e),s("div",me,[i.step===1?(n(),c(w,{key:0},[l(m,{size:"small",type:"blue",onClick:e[0]||(e[0]=y(g=>i.step=2,["stop"]))},{default:_(()=>[v(r(i.strings.yesILoveIt),1)]),_:1}),l(m,{size:"small",type:"gray",onClick:e[1]||(e[1]=y(g=>i.step=3,["stop"]))},{default:_(()=>[v(r(i.strings.notReally),1)]),_:1})],64)):u("",!0),i.step===2?(n(),c(w,{key:1},[l(m,{tag:"a",href:"https://wordpress.org/support/plugin/all-in-one-seo-pack/reviews/?filter=5#new-post",size:"small",type:"blue",target:"_blank",rel:"noopener noreferrer",onClick:e[2]||(e[2]=g=>o.processDismissNotification(!1))},{default:_(()=>[v(r(i.strings.okYouDeserveIt),1)]),_:1}),l(m,{size:"small",type:"gray",onClick:e[3]||(e[3]=y(g=>o.processDismissNotification(!0),["stop","prevent"]))},{default:_(()=>[v(r(i.strings.nopeMaybeLater),1)]),_:1})],64)):u("",!0),i.step===3?(n(),c(w,{key:2},[l(m,{tag:"a",href:o.feedbackUrl,size:"small",type:"blue",target:"_blank",rel:"noopener noreferrer",onClick:e[4]||(e[4]=g=>o.processDismissNotification(!1))},{default:_(()=>[v(r(i.strings.giveFeedback),1)]),_:1},8,["href"]),l(m,{size:"small",type:"gray",onClick:e[5]||(e[5]=y(g=>o.processDismissNotification(!1),["stop","prevent"]))},{default:_(()=>[v(r(i.strings.noThanks),1)]),_:1})],64)):u("",!0),a.notification.dismissed?u("",!0):(n(),c("a",{key:3,class:"dismiss",href:"#",onClick:e[6]||(e[6]=y(g=>o.processDismissNotification(!1),["stop","prevent"]))},r(i.strings.dismiss),1))])])])]),_:1},8,["active"])}const ge=k(de,[["render",pe]]);const ve={emits:["dismissed-notification"],components:{SvgCircleCheck:z,TransitionSlide:I},props:{notification:{type:Object,required:!0}},data(){return{active:!0,strings:{dismiss:this.$t.__("Dismiss",this.$td),yesILoveIt:this.$t.__("Yes, I love it!",this.$td),notReally:this.$t.__("Not Really...",this.$td),okYouDeserveIt:this.$t.__("Ok, you deserve it",this.$td),nopeMaybeLater:this.$t.__("Nope, maybe later",this.$td),giveFeedback:this.$t.__("Give feedback",this.$td),noThanks:this.$t.__("No thanks",this.$td)}}},computed:{...D(["options"]),title(){return this.$t.sprintf(this.$t.__("Are you enjoying %1$s?",this.$td),"AIOSEO")},content(){return this.$t.sprintf(this.$t.__("Hey, I noticed you have been using %1$s for some time - that’s awesome! Could you please do me a BIG favor and give it a 5-star rating on WordPress to help us spread the word and boost our motivation?",this.$td),"<strong>All in One SEO</strong>")+"<br><br><strong>~ Syed Balkhi<br>"+this.$t.sprintf(this.$t.__("CEO of %1$s",this.$td),"All in One SEO")+"</strong>"}},methods:{...B(["dismissNotifications","processButtonAction"]),processDismissNotification(t=!1){this.active=!1,this.dismissNotifications([this.notification.slug+(t?"-delay":"")]),this.$emit("dismissed-notification")}}},ye={class:"icon"},be={class:"body"},ke={class:"title"},$e=["innerHTML"],Ne={class:"actions"};function we(t,e,a,b,i,o){const h=d("svg-circle-check"),m=d("base-button"),f=d("transition-slide");return n(),p(f,{class:"aioseo-notification",active:i.active},{default:_(()=>[s("div",null,[s("div",ye,[l(h,{class:"success"})]),s("div",be,[s("div",ke,[s("div",null,r(o.title),1)]),s("div",{class:"notification-content",innerHTML:o.content},null,8,$e),s("div",Ne,[l(m,{tag:"a",href:"https://wordpress.org/support/plugin/all-in-one-seo-pack/reviews/?filter=5#new-post",size:"small",type:"blue",target:"_blank",rel:"noopener noreferrer",onClick:e[0]||(e[0]=g=>o.processDismissNotification(!1))},{default:_(()=>[v(r(i.strings.okYouDeserveIt),1)]),_:1}),l(m,{size:"small",type:"gray",onClick:e[1]||(e[1]=y(g=>o.processDismissNotification(!0),["stop","prevent"]))},{default:_(()=>[v(r(i.strings.nopeMaybeLater),1)]),_:1}),a.notification.dismissed?u("",!0):(n(),c("a",{key:0,class:"dismiss",href:"#",onClick:e[2]||(e[2]=y(g=>o.processDismissNotification(!1),["stop","prevent"]))},r(i.strings.dismiss),1))])])])]),_:1},8,["active"])}const Ce=k(ve,[["render",we]]);const Se={components:{SvgCircleClose:G,TransitionSlide:I},props:{notification:{type:Object,required:!0}},data(){return{active:!0,strings:{title:this.$t.sprintf(this.$t.__("%1$s %2$s Not Configured Properly",this.$td),"AIOSEO","Addons"),learnMore:this.$t.__("Learn More",this.$td),upgrade:this.$t.__("Upgrade",this.$td)}}},computed:{...D(["options"]),content(){let t="<ul>";return this.notification.addons.forEach(e=>{t+="<li><strong>AIOSEO - "+e.name+"</strong></li>"}),t+="</ul>",this.notification.message+t}}},De={class:"icon"},Le={class:"body"},Ae={class:"title"},Te=["innerHTML"],Pe={class:"actions"};function Be(t,e,a,b,i,o){const h=d("svg-circle-close"),m=d("base-button"),f=d("transition-slide");return n(),p(f,{class:"aioseo-notification",active:i.active},{default:_(()=>[s("div",null,[s("div",De,[l(h,{class:"error"})]),s("div",Le,[s("div",Ae,[s("div",null,r(i.strings.title),1)]),s("div",{class:"notification-content",innerHTML:o.content},null,8,Te),s("div",Pe,[l(m,{size:"small",type:"green",tag:"a",href:t.$links.utmUrl("notification-unlicensed-addons"),target:"_blank"},{default:_(()=>[v(r(i.strings.upgrade),1)]),_:1},8,["href"])])])])]),_:1},8,["active"])}const Ie=k(Se,[["render",Be]]);const Me={emits:["toggle-dismissed","dismissed-notification"],components:{CoreNotification:le,NotificationsReview:ge,NotificationsReview2:Ce,NotificationsUnlicensedAddons:Ie},props:{dismissedCount:{type:Number,required:!0},notifications:{type:Array,required:!0}},data(){return{dannieDetectiveImg:te,strings:{greatScott:this.$t.__("Great Scott! Where'd they all go?",this.$td),noNewNotifications:this.$t.__("You have no new notifications.",this.$td),seeDismissed:this.$t.__("See Dismissed Notifications",this.$td)}}}},Oe={class:"aioseo-notification-cards"},He={key:"no-notifications"},Ee={class:"no-notifications"},Ue=["src"],ze={class:"great-scott"},qe={class:"no-new-notifications"};function Ve(t,e,a,b,i,o){return n(),c("div",Oe,[a.notifications.length?(n(!0),c(w,{key:0},S(a.notifications,h=>(n(),p(q(h.component?h.component:"core-notification"),{key:h.slug,notification:h,ref_for:!0,ref:"notification",onDismissedNotification:e[0]||(e[0]=m=>t.$emit("dismissed-notification"))},null,40,["notification"]))),128)):u("",!0),a.notifications.length?u("",!0):(n(),c("div",He,[H(t.$slots,"no-notifications",{},()=>[s("div",Ee,[s("img",{alt:"Dannie the Detective",src:t.$getAssetUrl(i.dannieDetectiveImg)},null,8,Ue),s("div",ze,r(i.strings.greatScott),1),s("div",qe,r(i.strings.noNewNotifications),1),a.dismissedCount?(n(),c("a",{key:0,href:"#",class:"dismiss",onClick:e[1]||(e[1]=y(h=>t.$emit("toggle-dismissed"),["stop","prevent"]))},r(i.strings.seeDismissed),1)):u("",!0)])])]))])}const Re=k(Me,[["render",Ve]]);const Ge={components:{CoreNotificationCards:Re,SvgClose:R},mixins:[W],data(){return{dismissed:!1,maxNotifications:Number.MAX_SAFE_INTEGER,currentPage:0,totalPages:1,strings:{dismissedNotifications:this.$t.__("Dismissed Notifications",this.$td),dismissAll:this.$t.__("Dismiss All",this.$td)}}},watch:{showNotifications(t){t?(this.currentPage=0,this.setMaxNotifications(),this.addBodyClass()):this.removeBodyClass()},dismissed(){this.setMaxNotifications()},notifications(){this.setMaxNotifications()}},computed:{...D(["showNotifications"]),filteredNotifications(){return[...this.notifications].splice(this.currentPage===0?0:this.currentPage*this.maxNotifications,this.maxNotifications)},pages(){const t=[];for(let e=0;e<this.totalPages;e++)t.push({number:e+1});return t}},methods:{...B(["dismissNotifications"]),...V(["toggleNotifications"]),escapeListener(t){t.key==="Escape"&&this.showNotifications&&this.toggleNotifications()},addBodyClass(){document.body.classList.add("aioseo-show-notifications")},removeBodyClass(){document.body.classList.remove("aioseo-show-notifications")},documentClick(t){if(!this.showNotifications)return;const e=t&&t.target?t.target:null,a=document.querySelector("#wp-admin-bar-aioseo-notifications");if(a&&(a===e||a.contains(e)))return;const b=document.querySelector("#toplevel_page_aioseo .wp-first-item"),i=document.querySelector("#toplevel_page_aioseo .wp-first-item .aioseo-menu-notification-indicator");if(b&&b.contains(i)&&(b===e||b.contains(e)))return;const o=this.$refs["aioseo-notifications"];o&&(o===e||o.contains(e))||this.toggleNotifications()},notificationsLinkClick(t){t.preventDefault(),this.toggleNotifications()},processDismissAllNotifications(){const t=[];this.notifications.forEach(e=>{t.push(e.slug)}),this.dismissNotifications(t).then(()=>{this.setMaxNotifications()})},setMaxNotifications(){const t=this.currentPage;this.currentPage=0,this.totalPages=1,this.maxNotifications=Number.MAX_SAFE_INTEGER,this.$nextTick(async()=>{const e=[],a=document.querySelectorAll(".notification-menu .aioseo-notification");a&&a.forEach(i=>{let o=i.offsetHeight;const h=window.getComputedStyle?getComputedStyle(i,null):i.currentStyle,m=parseInt(h.marginTop)||0,f=parseInt(h.marginBottom)||0;o+=m+f,e.push(o)});const b=document.querySelector(".notification-menu .aioseo-notification-cards");if(b){let i=0,o=0;for(let h=0;h<e.length&&(o+=e[h],!(o>b.offsetHeight));h++)i++;this.maxNotifications=i||1,this.totalPages=Math.ceil(e.length/i)}this.currentPage=t>this.totalPages-1?this.totalPages-1:t})}},mounted(){document.addEventListener("keydown",this.escapeListener),document.addEventListener("click",this.documentClick);const t=document.querySelector("#wp-admin-bar-aioseo-notifications .ab-item");t&&t.addEventListener("click",this.notificationsLinkClick);const e=document.querySelector("#toplevel_page_aioseo .wp-first-item"),a=document.querySelector("#toplevel_page_aioseo .wp-first-item .aioseo-menu-notification-indicator");e&&a&&e.addEventListener("click",this.notificationsLinkClick)}},je={class:"aioseo-notifications",ref:"aioseo-notifications"},Fe={key:0,class:"notification-menu"},Ke={class:"notification-header"},Ye={class:"new-notifications"},We={class:"dismissed-notifications"},Xe={class:"notification-footer"},Je={class:"pagination"},Qe=["onClick"],Ze={key:0,class:"dismiss-all"};function xe(t,e,a,b,i,o){const h=d("svg-close"),m=d("core-notification-cards");return n(),c("div",je,[l(E,{name:"notifications-slide"},{default:_(()=>[t.showNotifications?(n(),c("div",Fe,[s("div",Ke,[s("span",Ye,"("+r(t.notificationsCount)+") "+r(t.notificationTitle),1),s("div",We,[!i.dismissed&&t.dismissedNotificationsCount?(n(),c("a",{key:0,href:"#",onClick:e[0]||(e[0]=y(f=>i.dismissed=!0,["stop","prevent"]))},r(i.strings.dismissedNotifications),1)):u("",!0),i.dismissed&&t.dismissedNotificationsCount?(n(),c("a",{key:1,href:"#",onClick:e[1]||(e[1]=y(f=>i.dismissed=!1,["stop","prevent"]))},r(i.strings.activeNotifications),1)):u("",!0)]),s("div",{onClick:e[2]||(e[2]=y((...f)=>t.toggleNotifications&&t.toggleNotifications(...f),["stop"]))},[l(h)])]),l(m,{class:"notification-cards",notifications:o.filteredNotifications,dismissedCount:t.dismissedNotificationsCount,onToggleDismissed:e[3]||(e[3]=f=>i.dismissed=!i.dismissed)},null,8,["notifications","dismissedCount"]),s("div",Xe,[s("div",Je,[i.totalPages>1?(n(!0),c(w,{key:0},S(o.pages,(f,g)=>(n(),c("div",{class:P(["page-number",{active:f.number===1+i.currentPage}]),key:g,onClick:L=>i.currentPage=f.number-1},r(f.number),11,Qe))),128)):u("",!0)]),i.dismissed?u("",!0):(n(),c("div",Ze,[t.notifications.length?(n(),c("a",{key:0,href:"#",class:"dismiss",onClick:e[4]||(e[4]=y((...f)=>o.processDismissAllNotifications&&o.processDismissAllNotifications(...f),["stop","prevent"]))},r(i.strings.dismissAll),1)):u("",!0)]))])])):u("",!0)]),_:1}),l(E,{name:"notifications-fade"},{default:_(()=>[t.showNotifications?(n(),c("div",{key:0,onClick:e[5]||(e[5]=(...f)=>t.toggleNotifications&&t.toggleNotifications(...f)),class:"overlay"})):u("",!0)]),_:1})],512)}const ts=k(Ge,[["render",xe]]),es={components:{CoreHeader:Q,CoreHelp:xt,CoreMainTabs:nt,CoreNotifications:ts,GridContainer:Z},mixins:[X],props:{pageName:{type:String,required:!0},showTabs:{type:Boolean,default(){return!0}},showSaveButton:{type:Boolean,default(){return!0}},excludeTabs:{type:Array,default(){return[]}},containerClasses:{type:Array,default(){return[]}}},data(){return{tabsKey:0,strings:{saveChanges:this.$t.__("Save Changes",this.$td)}}},watch:{excludeTabs(){this.tabsKey+=1}},computed:{...U(["settings"]),...D(["loading","options","showNotifications","helpPanel","notifications"]),tabs(){return this.$router.options.routes.filter(t=>t.name&&t.meta&&t.meta.name).filter(t=>this.$allowed(t.meta.access)).filter(t=>!t.meta.license||this.$license.hasMinimumLevel(this.$aioseo,t.meta.license)).filter(t=>!(t.meta.display==="lite"&&this.$isPro||t.meta.display==="pro"&&!this.$isPro)).filter(t=>!this.excludeTabs.includes(t.name)).map(t=>({slug:t.name,name:t.meta.name,url:{name:t.name},access:t.meta.access,pro:!!t.meta.pro}))},shouldShowSaveButton(){if(this.$route&&this.$route.name){const t=this.$router.options.routes.find(e=>e.name===this.$route.name);if(t&&t.meta&&t.meta.hideSaveButton)return!1}return this.showSaveButton}},methods:{...V(["toggleNotifications","disableForceShowNotifications"])},mounted(){K().notifications&&(this.showNotifications||this.toggleNotifications(),setTimeout(()=>{Y("notifications")},500)),this.notifications.force&&this.notifications.active.length&&(this.disableForceShowNotifications(),this.toggleNotifications())}},ss={class:"aioseo-main"},is={key:1,class:"save-changes"};function os(t,e,a,b,i,o){const h=d("core-notifications"),m=d("core-header"),f=d("core-main-tabs"),g=d("base-button"),L=d("grid-container"),M=d("core-help");return n(),c("div",null,[l(h),s("div",ss,[l(m,{"page-name":a.pageName},null,8,["page-name"]),l(L,{class:P(a.containerClasses)},{default:_(()=>[a.showTabs?(n(),p(f,{key:i.tabsKey,tabs:o.tabs,showSaveButton:o.shouldShowSaveButton},{extra:_(()=>[H(t.$slots,"extra")]),_:3},8,["tabs","showSaveButton"])):u("",!0),l(E,{name:"route-fade",mode:"out-in"},{default:_(()=>[H(t.$slots,"default")]),_:3}),o.shouldShowSaveButton?(n(),c("div",is,[l(g,{type:"blue",size:"medium",loading:t.loading,onClick:t.processSaveChanges},{default:_(()=>[v(r(i.strings.saveChanges),1)]),_:1},8,["loading","onClick"])])):u("",!0)]),_:3},8,["class"])]),t.helpPanel.docs&&Object.keys(t.helpPanel.docs).length?(n(),p(M,{key:0})):u("",!0)])}const Cs=k(es,[["render",os]]);export{Cs as C,Re as a};
