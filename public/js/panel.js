(() => {
    'use strict';

    const qs = (s, root = document) => root.querySelector(s);
    const qsa = (s, root = document) => [...root.querySelectorAll(s)];
    const csrf = qs('meta[name="csrf-token"]')?.content || '';
    const faDigits = '۰۱۲۳۴۵۶۷۸۹';
    const toFa = value => String(value ?? '').replace(/[0-9]/g, d => faDigits[d]);
    const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c]));

    const toast = (message, type = 'success') => {
        const stack = qs('[data-toast-stack]');
        if (!stack) return;
        const node = document.createElement('div');
        node.className = `toast ${type}`;
        node.textContent = message;
        stack.appendChild(node);
        setTimeout(() => node.remove(), 3600);
    };

    // Mobile navigation
    const sidebar = qs('[data-sidebar]');
    const backdrop = qs('[data-mobile-backdrop]');
    const mobileOpen = () => { sidebar?.classList.add('open'); backdrop?.classList.add('show'); document.body.style.overflow = 'hidden'; };
    const mobileClose = () => { sidebar?.classList.remove('open'); backdrop?.classList.remove('show'); document.body.style.overflow = ''; };
    qs('[data-sidebar-open]')?.addEventListener('click', mobileOpen);
    qs('[data-sidebar-close]')?.addEventListener('click', mobileClose);
    backdrop?.addEventListener('click', mobileClose);
    qsa('.sidebar a').forEach(a => a.addEventListener('click', mobileClose));

    // Flash messages
    qsa('[data-dismiss]').forEach(button => button.addEventListener('click', () => button.closest('[data-flash]')?.remove()));
    setTimeout(() => qsa('[data-flash]').forEach(el => { el.style.opacity = '.7'; }), 7000);

    // Theme
    const themeKey = 'instagram-panel-theme';
    const storedTheme = localStorage.getItem(themeKey);
    if (storedTheme === 'dark') document.documentElement.dataset.theme = 'dark';
    qs('[data-theme-toggle]')?.addEventListener('click', () => {
        const dark = document.documentElement.dataset.theme === 'dark';
        document.documentElement.dataset.theme = dark ? '' : 'dark';
        localStorage.setItem(themeKey, dark ? 'light' : 'dark');
    });

    // Confirmation modal
    const confirmModal = qs('[data-confirm-modal]');
    let confirmForm = null;
    qsa('[data-confirm-form]').forEach(button => button.addEventListener('click', () => {
        confirmForm = document.getElementById(button.dataset.confirmForm);
        if (!confirmModal || !confirmForm) return;
        qs('[data-confirm-title]', confirmModal).textContent = button.dataset.confirmTitle || 'حذف مورد';
        qs('[data-confirm-text]', confirmModal).textContent = button.dataset.confirmText || 'این عملیات قابل بازگشت نیست.';
        confirmModal.classList.add('is-open');
        confirmModal.setAttribute('aria-hidden', 'false');
    }));
    qs('[data-confirm-cancel]')?.addEventListener('click', () => { confirmForm = null; confirmModal?.classList.remove('is-open'); });
    qs('[data-confirm-submit]')?.addEventListener('click', () => { if (confirmForm) confirmForm.submit(); });
    confirmModal?.addEventListener('click', e => { if (e.target === confirmModal) confirmModal.classList.remove('is-open'); });

    // Copy utilities
    const copyText = async (text) => {
        try { await navigator.clipboard.writeText(text || ''); toast('کپی شد.'); }
        catch { toast('کپی انجام نشد.', 'error'); }
    };
    qsa('[data-copy]').forEach(el => el.addEventListener('click', () => copyText(el.dataset.copy || '')));
    qsa('[data-copy-target]').forEach(el => el.addEventListener('click', () => copyText(qs(el.dataset.copyTarget)?.value || qs(el.dataset.copyTarget)?.textContent || '')));
    qsa('[data-focus-search]').forEach(el => el.addEventListener('click', () => { const target = qs(el.dataset.focusSearch); target?.focus(); target?.scrollIntoView({behavior:'smooth', block:'center'}); }));

    // Notifications
    const notificationButton = qs('[data-notifications]');
    const notificationPanel = qs('[data-notification-panel]');
    if (notificationButton && notificationPanel) {
        let loaded = false;
        notificationButton.addEventListener('click', async e => {
            e.stopPropagation();
            notificationPanel.classList.toggle('open');
            if (loaded) return;
            loaded = true;
            notificationPanel.innerHTML = '<div class="notification-item"><strong>در حال دریافت…</strong><span>لطفاً کمی صبر کن.</span></div>';
            try {
                const res = await fetch(qs('.app-shell')?.dataset.notificationsUrl || '/notifications', {headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}});
                const items = await res.json();
                notificationPanel.innerHTML = Array.isArray(items) && items.length
                    ? items.slice(0,12).map(item => `<div class="notification-item"><strong>${escapeHtml(item.title || item.type || 'اعلان')}</strong><span>${escapeHtml(item.body || item.message || 'رویداد جدید ثبت شده است.')}</span></div>`).join('')
                    : '<div class="notification-item"><strong>اعلان جدیدی نیست</strong><span>در حال حاضر موردی برای نمایش وجود ندارد.</span></div>';
            } catch { notificationPanel.innerHTML = '<div class="notification-item"><strong>دریافت اعلان انجام نشد</strong><span>بعداً دوباره امتحان کن.</span></div>'; }
        });
        document.addEventListener('click', e => { if (!notificationPanel.contains(e.target) && !notificationButton.contains(e.target)) notificationPanel.classList.remove('open'); });
    }

    // Character counters
    const updateCounter = (input, counter) => {
        const max = Number(input.maxLength || 0);
        counter.textContent = `${toFa(input.value.length)}${max ? ` / ${toFa(max)}` : ''}`;
    };
    qsa('[data-counter-for]').forEach(counter => {
        const input = qs(counter.dataset.counterFor);
        if (!input) return;
        updateCounter(input, counter);
        input.addEventListener('input', () => updateCounter(input, counter));
    });

    // Choice cards
    qsa('.choice-card,.goal-option,.account-choice,.publish-type-card,.publish-mode,.preset-card').forEach(card => {
        const input = qs('input[type="radio"]', card) || qs('input[type="checkbox"]', card);
        if (!input) return;
        const refresh = () => {
            if (input.type === 'radio') {
                qsa(`input[name="${CSS.escape(input.name)}"]`).forEach(other => other.closest('label')?.classList.toggle('selected', other.checked));
            }
            card.classList.toggle('selected', !!input.checked);
        };
        input.addEventListener('change', refresh);
        refresh();
    });

    // Token insertion
    qsa('[data-insert-token]').forEach(button => button.addEventListener('click', () => {
        const target = qs(button.dataset.insertTarget || '#privateReply');
        if (!target) return;
        const value = button.dataset.insertToken || '';
        const start = target.selectionStart ?? target.value.length;
        const end = target.selectionEnd ?? target.value.length;
        target.value = `${target.value.slice(0,start)}${value}${target.value.slice(end)}`;
        target.focus();
        target.selectionStart = target.selectionEnd = start + value.length;
        target.dispatchEvent(new Event('input', {bubbles:true}));
    }));

    // پاسخ‌های سریع و سازنده حرفه‌ای دکمه‌ها
    const addRepeaterRow = (container, kind, values = {}) => {
        const row = document.createElement('div');
        row.className = 'repeater-row';
        const title = document.createElement('input');
        title.dataset.field = 'title';
        title.value = values.title || '';
        title.placeholder = kind === 'button' ? 'عنوان دکمه' : 'متن پاسخ سریع';
        row.appendChild(title);
        const remove = document.createElement('button');
        remove.type='button';
        remove.className='repeater-remove';
        remove.textContent='×';
        remove.addEventListener('click',()=>row.remove());
        row.appendChild(remove);
        container.appendChild(row);
    };
    qsa('[data-repeater-add]').forEach(button => button.addEventListener('click', () => {
        const name = button.dataset.repeaterAdd;
        const container = qs(`[data-repeater="${name}"]`);
        if (container && name !== 'button') addRepeaterRow(container, 'quick');
    }));
    qsa('.repeater-remove').forEach(button => button.addEventListener('click', () => button.closest('.repeater-row')?.remove()));
    const serializeRepeater = (name) => qsa(`[data-repeater="${name}"] .repeater-row`).map(row => {
        const obj = {};
        qsa('[data-field]', row).forEach(input => obj[input.dataset.field] = input.value.trim());
        return obj;
    }).filter(obj => obj.title);

    const getBuilderAccountId = builder => {
        const explicit = builder.dataset.accountId || '';
        const selected = qs('input[name="instagram_account_id"]:checked')?.value || '';
        return explicit || selected;
    };
    const buttonWorkflowPayload = id => id ? `zernio:workflow:${id}` : '';

    const updateButtonBuilderIndexes = builder => {
        const rows = qsa('[data-button-row]', builder);
        rows.forEach((row, index) => {
            row.dataset.index = index;
            const indexNode = qs('[data-button-index]', row);
            if (indexNode) indexNode.textContent = toFa(index + 1);
        });
        const count = qs('[data-button-count]', builder);
        if (count) count.textContent = `${toFa(rows.length)} / ۳`;
        const add = qs('[data-button-add]', builder);
        if (add) add.disabled = rows.length >= 3;
    };

    const renderButtonPreview = builder => {
        const box = qs('[data-button-preview-actions]', builder);
        if (!box) return;
        const buttons = qsa('[data-button-row]', builder).map(row => ({
            title: qs('[data-button-title]', row)?.value.trim() || '',
            type: qs('[data-button-type]', row)?.value || 'url',
        })).filter(item => item.title);
        box.innerHTML = buttons.length
            ? buttons.map(item => `<span class="button-preview-pill">${escapeHtml(item.title)}<small>${item.type === 'postback' ? 'مسیر گفتگو' : 'لینک'}</small></span>`).join('')
            : '<span class="button-preview-empty">هنوز دکمه‌ای اضافه نشده است.</span>';
    };

    const loadBuilderWorkflows = async builder => {
        const accountId = getBuilderAccountId(builder);
        const selects = qsa('[data-button-workflow]', builder);
        if (!selects.length) return;
        if (!accountId) {
            selects.forEach(select => { select.innerHTML = '<option value="">ابتدا حساب را انتخاب کن</option>'; });
            return;
        }
        selects.forEach(select => { select.innerHTML = '<option value="">در حال دریافت مسیرها…</option>'; });
        try {
            const response = await fetch(`/automations/workflows?account_id=${encodeURIComponent(accountId)}`, {headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}});
            const data = await response.json().catch(() => ({}));
            if (!response.ok || !data.ok) throw new Error(data.message || 'مسیرهای گفتگو دریافت نشد.');
            const options = data.items || [];
            selects.forEach(select => {
                const previous = select.value || select.closest('[data-button-row]')?.dataset.workflowId || '';
                select.innerHTML = '<option value="">انتخاب مسیر</option>' + options.map(item => `<option value="${escapeHtml(item.id)}">${escapeHtml(item.name)}</option>`).join('');
                if (previous && options.some(item => String(item.id) === String(previous))) select.value = previous;
            });
        } catch (error) {
            selects.forEach(select => { select.innerHTML = `<option value="">${escapeHtml(error.message || 'دریافت نشد')}</option>`; });
        }
    };

    const decorateButtonRow = (row, builder) => {
        qs('[data-button-remove]', row)?.addEventListener('click', () => {
            row.remove();
            updateButtonBuilderIndexes(builder);
            renderButtonPreview(builder);
        });
        qs('[data-button-type]', row)?.addEventListener('change', async e => {
            const isPostback = e.target.value === 'postback';
            const urlWrap = qs('[data-button-url-wrap]', row);
            const workflowWrap = qs('[data-button-workflow-wrap]', row);
            if (urlWrap) urlWrap.hidden = isPostback;
            if (workflowWrap) workflowWrap.hidden = !isPostback;
            if (isPostback) await loadBuilderWorkflows(builder);
            renderButtonPreview(builder);
        });
        qsa('[data-button-title],[data-button-url],[data-button-workflow]', row).forEach(input => input.addEventListener('input', () => renderButtonPreview(builder)));
        qsa('[data-button-title],[data-button-url],[data-button-workflow]', row).forEach(input => input.addEventListener('change', () => renderButtonPreview(builder)));
    };

    const createButtonRow = (builder, values = {}) => {
        if (qsa('[data-button-row]', builder).length >= 3) {
            toast('حداکثر ۳ دکمه می‌توانی اضافه کنی.', 'error');
            return;
        }
        const list = qs('[data-button-list]', builder);
        if (!list) return;
        const row = document.createElement('div');
        row.className = 'button-builder-row';
        row.dataset.buttonRow = '';
        row.dataset.workflowId = values.workflowId || '';
        row.innerHTML = `
            <button type="button" class="button-drag" aria-label="جابجایی">⋮⋮</button>
            <span class="button-index" data-button-index></span>
            <div class="button-row-fields">
                <div class="button-field"><label>عنوان</label><input type="text" maxlength="20" data-button-title placeholder="مثلاً مشاهده قیمت" value="${escapeHtml(values.title || '')}"><small>حداکثر ۲۰ کاراکتر</small></div>
                <div class="button-field"><label>نوع</label><select data-button-type><option value="url" ${values.type === 'postback' ? '' : 'selected'}>باز کردن لینک</option><option value="postback" ${values.type === 'postback' ? 'selected' : ''}>ادامه مسیر گفتگو</option></select></div>
                <div class="button-field" data-button-url-wrap ${values.type === 'postback' ? 'hidden' : ''}><label>لینک</label><input type="url" data-button-url placeholder="https://example.com" value="${escapeHtml(values.url || '')}"></div>
                <div class="button-field" data-button-workflow-wrap ${values.type !== 'postback' ? 'hidden' : ''}><label>مسیر گفتگو</label><select data-button-workflow><option value="">در حال دریافت مسیرها…</option></select></div>
            </div>
            <button type="button" class="button-remove" data-button-remove aria-label="حذف دکمه">×</button>`;
        list.appendChild(row);
        decorateButtonRow(row, builder);
        updateButtonBuilderIndexes(builder);
        renderButtonPreview(builder);
        if (values.type === 'postback') loadBuilderWorkflows(builder);
    };

    const initButtonBuilder = builder => {
        qsa('[data-button-row]', builder).forEach(row => {
            const payload = row.dataset.workflowId || '';
            const select = qs('[data-button-workflow]', row);
            if (select && payload) row.dataset.workflowId = payload;
            decorateButtonRow(row, builder);
        });
        qs('[data-button-add]', builder)?.addEventListener('click', () => createButtonRow(builder));
        updateButtonBuilderIndexes(builder);
        renderButtonPreview(builder);
        if (qsa('[data-button-type]', builder).some(select => select.value === 'postback')) loadBuilderWorkflows(builder);
    };

    const serializeButtonBuilder = builder => qsa('[data-button-row]', builder).map(row => {
        const title = qs('[data-button-title]', row)?.value.trim() || '';
        const type = qs('[data-button-type]', row)?.value || 'url';
        const url = qs('[data-button-url]', row)?.value.trim() || '';
        const workflow = qs('[data-button-workflow]', row)?.value || row.dataset.workflowId || '';
        if (!title) return null;
        if (type === 'postback') {
            if (!workflow) return null;
            return {type:'postback', title, payload:buttonWorkflowPayload(workflow)};
        }
        if (!url) return null;
        return {type:'url', title, url};
    }).filter(Boolean).slice(0,3);

    qsa('[data-button-builder]').forEach(initButtonBuilder);

    // Message attachment + voice recorder
    const typeLabel = {image:'تصویر',video:'ویدیو',audio:'ویس / صوت',file:'فایل'};
    const detectAttachmentType = file => {
        const mime=(file?.type||'').toLowerCase();
        if(mime.startsWith('image/')) return 'image'; if(mime.startsWith('video/')) return 'video'; if(mime.startsWith('audio/')) return 'audio'; return 'file';
    };
    const iconForType = type => type==='video'?'▶':type==='audio'?'◉':type==='image'?'▧':'□';
    const encodeWav = buffer => {
        const channel = buffer.getChannelData(0); const rate=buffer.sampleRate; const bytes=2; const dataLen=channel.length*bytes; const arr=new ArrayBuffer(44+dataLen); const view=new DataView(arr);
        const ws=(off,str)=>[...str].forEach((c,i)=>view.setUint8(off+i,c.charCodeAt(0)));
        ws(0,'RIFF');view.setUint32(4,36+dataLen,true);ws(8,'WAVE');ws(12,'fmt ');view.setUint32(16,16,true);view.setUint16(20,1,true);view.setUint16(22,1,true);view.setUint32(24,rate,true);view.setUint32(28,rate*2,true);view.setUint16(32,2,true);view.setUint16(34,16,true);ws(36,'data');view.setUint32(40,dataLen,true);
        let off=44; for(let i=0;i<channel.length;i++,off+=2){const sample=Math.max(-1,Math.min(1,channel[i]));view.setInt16(off,sample<0?sample*0x8000:sample*0x7fff,true);} return new Blob([view],{type:'audio/wav'});
    };
    const blobToWav = async blob => { const Ctx=window.AudioContext||window.webkitAudioContext; if(!Ctx) throw new Error('unsupported'); const context=new Ctx(); try{return encodeWav(await context.decodeAudioData(await blob.arrayBuffer()));} finally{context.close?.();} };

    qsa('[data-message-composer]').forEach(composer => {
        const fileInput=qs('[data-composer-file]',composer), pick=qs('[data-composer-pick]',composer), record=qs('[data-composer-record]',composer), stop=qs('[data-composer-stop]',composer), timer=qs('[data-composer-timer]',composer), orb=qs('[data-composer-orb]',composer);
        const uploadUrl=qs('.app-shell')?.dataset?.uploadUrl || composer.dataset.uploadUrl;
        let recorder=null,stream=null,chunks=[],timerId=null,startedAt=0;
        const statusNode=qs('[data-composer-status]',composer);
        const setStatus=(text,kind='')=>{if(!statusNode)return;statusNode.hidden=false;statusNode.className=`composer-upload-status ${kind}`;statusNode.textContent=text;};
        const currentTrigger=()=>composer.dataset.automationTrigger||qs('input[name="trigger"]:checked')?.value||'';
        const render=()=>{
            const url=qs('[data-composer-url]',composer)?.value||'',type=qs('[data-composer-type]',composer)?.value||'',name=qs('[data-composer-name]',composer)?.value||'رسانه انتخاب‌شده'; const box=qs('[data-composer-preview]',composer),media=qs('[data-composer-preview-media]',composer);
            if(!box)return; if(!url){box.hidden=true;return;} box.hidden=false; qs('[data-composer-preview-name]',composer).textContent=name; qs('[data-composer-preview-type]',composer).textContent=typeLabel[type]||'رسانه'; qs('[data-composer-preview-icon]',composer).textContent=iconForType(type); if(media){media.innerHTML=''; if(type==='image'){const img=new Image();img.src=url;media.appendChild(img);}else if(type==='video'){const v=document.createElement('video');v.src=url;v.muted=true;v.playsInline=true;media.appendChild(v);}else if(type==='audio'){const a=document.createElement('audio');a.controls=true;a.src=url;media.appendChild(a);}}
        };
        const upload=async file=>{
            if(!file)return; if(file.size>25*1024*1024){setStatus('حجم فایل بیشتر از ۲۵ مگابایت است.','error');return;}
            const type=detectAttachmentType(file); if(!['image','video','audio'].includes(type)){setStatus('این نوع فایل قابل ارسال نیست.','error');return;}
            setStatus('در حال آماده‌سازی رسانه…'); const form=new FormData(); form.append('file',file,file.name||`media-${Date.now()}`);
            try{const res=await fetch(composer.dataset.uploadUrl,{method:'POST',headers:{'X-CSRF-TOKEN':csrf,Accept:'application/json'},body:form});const data=await res.json().catch(()=>({}));if(!res.ok||!data.ok||!data.url)throw new Error(data.message||'بارگذاری رسانه انجام نشد.');qs('[data-composer-url]',composer).value=data.url;qs('[data-composer-type]',composer).value=type;qs('[data-composer-name]',composer).value=data.filename||file.name||'';setStatus('رسانه آماده ارسال است.','success');render();}catch(err){setStatus(err.message||'بارگذاری رسانه انجام نشد.','error');}
        };
        const sequenceInput=qs('[data-composer-sequence]',composer), sequenceList=qs('[data-composer-sequence-list]',composer);
        let sequence=[];
        try{sequence=JSON.parse(sequenceInput?.value||'[]');if(!Array.isArray(sequence))sequence=[];}catch{sequence=[];}
        const messageField=()=>composer.closest('form')?.querySelector('[name="dm_message"],[name="private_reply"]');
        const renderSequence=()=>{
            if(!sequenceList)return;
            sequenceList.innerHTML=sequence.length?sequence.map((item,i)=>{
                const isMedia=item.type==='media';
                const label=isMedia?(item.attachmentType==='audio'?'🎙 ویس':item.attachmentType==='video'?'🎬 ویدیو':'🖼 تصویر'):'💬 متن';
                const preview=isMedia?(item.attachmentName||'رسانه انتخاب‌شده'):(item.message||'متن خالی');
                return '<div class="composer-sequence-item"><span class="sequence-number">'+(i+1)+'</span><span class="sequence-kind">'+label+'</span><strong>'+escapeHtml(preview).slice(0,90)+'</strong><button type="button" class="icon-button" data-sequence-remove="'+i+'" title="حذف">×</button></div>';
            }).join(''):'<div class="empty-mini">هنوز پیامی به صف اضافه نشده.</div>';
            if(sequenceInput)sequenceInput.value=JSON.stringify(sequence);
            qsa('[data-sequence-remove]',sequenceList).forEach(btn=>btn.addEventListener('click',()=>{sequence.splice(Number(btn.dataset.sequenceRemove),1);renderSequence();}));
        };
        const addCurrentToSequence=()=>{
            const field=messageField(), text=(field?.value||'').trim();
            const url=qs('[data-composer-url]',composer)?.value||'';
            const type=qs('[data-composer-type]',composer)?.value||'';
            const name=qs('[data-composer-name]',composer)?.value||'';
            if(text) sequence.push({type:'text',message:text});
            if(url) sequence.push({type:'media',attachmentUrl:url,attachmentType:type||'file',attachmentName:name});
            if(!text&&!url){field?.focus();setStatus('ابتدا متن یا رسانه را آماده کن.','error');return;}
            if(field)field.value='';
            qs('[data-composer-url]',composer).value='';qs('[data-composer-type]',composer).value='';qs('[data-composer-name]',composer).value='';
            if(statusNode)statusNode.hidden=true;render();renderSequence();
        };
        qs('[data-composer-add-current]',composer)?.addEventListener('click',addCurrentToSequence);
        qs('[data-composer-add-text]',composer)?.addEventListener('click',()=>{
            const field=messageField();
            if((field?.value||'').trim()){addCurrentToSequence();return;}
            field?.focus();setStatus('متن را بنویس و سپس «افزودن آیتم فعلی» را بزن.','');
        });
        composer.closest('form')?.addEventListener('submit',()=>{
            const field=messageField(), text=(field?.value||'').trim();
            const url=qs('[data-composer-url]',composer)?.value||'';
            const type=qs('[data-composer-type]',composer)?.value||'';
            const name=qs('[data-composer-name]',composer)?.value||'';
            if(text){
                if(sequence.length===1 && sequence[0]?.type==='text') sequence[0].message=text;
                else sequence.push({type:'text',message:text});
            }
            if(url){
                if(sequence.length===1 && sequence[0]?.type==='media') Object.assign(sequence[0],{attachmentUrl:url,attachmentType:type||'file',attachmentName:name});
                else sequence.push({type:'media',attachmentUrl:url,attachmentType:type||'file',attachmentName:name});
            }
            if(sequenceInput)sequenceInput.value=JSON.stringify(sequence);
        });
        renderSequence();
        const setAvailability=()=>{const disabled=currentTrigger()==='comment'; composer.classList.toggle('is-disabled',disabled);qsa('[data-composer-tab="media"],[data-composer-tab="voice"],[data-composer-pick],[data-composer-record],[data-composer-stop]',composer).forEach(node=>node.disabled=disabled);const note=qs('[data-composer-disabled]',composer);if(note)note.hidden=!disabled;if(disabled){qs('[data-composer-tab="text"]',composer)?.click();qs('[data-composer-url]',composer).value='';qs('[data-composer-type]',composer).value='';qs('[data-composer-name]',composer).value='';render();}};
        qsa('[data-composer-tab]',composer).forEach(tab=>tab.addEventListener('click',()=>{if(tab.disabled)return;qsa('[data-composer-tab]',composer).forEach(t=>t.classList.toggle('is-active',t===tab));qsa('[data-composer-panel]',composer).forEach(panel=>panel.hidden=panel.dataset.composerPanel!==tab.dataset.composerTab);}));
        pick?.addEventListener('click',()=>fileInput?.click()); fileInput?.addEventListener('change',()=>{const file=fileInput.files?.[0];if(file)upload(file);fileInput.value='';});
        qs('[data-composer-clear]',composer)?.addEventListener('click',()=>{qs('[data-composer-url]',composer).value='';qs('[data-composer-type]',composer).value='';qs('[data-composer-name]',composer).value='';if(statusNode)statusNode.hidden=true;render();});
        record?.addEventListener('click',async()=>{
            if(currentTrigger()==='comment')return;if(!navigator.mediaDevices?.getUserMedia||!window.MediaRecorder){setStatus('این مرورگر ضبط صدا را پشتیبانی نمی‌کند.','error');return;}
            try{stream=await navigator.mediaDevices.getUserMedia({audio:true});const mime=['audio/webm;codecs=opus','audio/webm','audio/mp4'].find(t=>MediaRecorder.isTypeSupported?.(t));recorder=new MediaRecorder(stream,mime?{mimeType:mime}:undefined);chunks=[];startedAt=Date.now();recorder.ondataavailable=e=>e.data?.size&&chunks.push(e.data);recorder.onstop=async()=>{clearInterval(timerId);record.disabled=false;stop.disabled=true;orb?.classList.remove('composer-pulse');stream?.getTracks().forEach(t=>t.stop());stream=null;try{setStatus('در حال آماده‌سازی ویس…');const source=new Blob(chunks,{type:recorder.mimeType||'audio/webm'});const wav=await blobToWav(source);await upload(new File([wav],`voice-${Date.now()}.wav`,{type:'audio/wav'}));}catch{setStatus('تبدیل ویس انجام نشد؛ فایل صوتی سازگار را بارگذاری کن.','error');}};recorder.start(250);record.disabled=true;stop.disabled=false;orb?.classList.add('composer-pulse');timerId=setInterval(()=>{const sec=Math.floor((Date.now()-startedAt)/1000);if(timer)timer.textContent=`${toFa(String(Math.floor(sec/60)).padStart(2,'0'))}:${toFa(String(sec%60).padStart(2,'0'))}`;if(sec>=60&&recorder?.state==='recording')recorder.stop();},250);}catch{setStatus('دسترسی به میکروفن داده نشد.','error');}
        });
        stop?.addEventListener('click',()=>{if(recorder?.state==='recording')recorder.stop();}); setAvailability(); render(); composer.addEventListener('automationtriggerchange',setAvailability);
    });
    qsa('input[name="trigger"]').forEach(input=>input.addEventListener('change',()=>qsa('[data-message-composer]').forEach(c=>{c.dataset.automationTrigger=input.checked?input.value:c.dataset.automationTrigger; c.dispatchEvent(new CustomEvent('automationtriggerchange')); } )));

    // Automation studio create
    const createForm=qs('#automationCreateForm');
    if(createForm){
        let currentStep=1, mediaItems=[];
        const panels=qsa('[data-step-panel]',createForm), progress=qsa('[data-go-step]');
        const goStep=step=>{let next=Number(step);const direct=qs('input[name="trigger"]:checked',createForm)?.value==='direct_message';if(direct&&next===2)next=3;if(direct&&next===2)next=3;if(direct&&next===1&&currentStep===3)next=1;currentStep=next;panels.forEach(p=>p.classList.toggle('is-active',Number(p.dataset.stepPanel)===currentStep));progress.forEach(p=>p.classList.toggle('active',Number(p.dataset.goStep)===currentStep));window.scrollTo({top:0,behavior:'smooth'});};
        qsa('[data-next-step]').forEach(b=>b.addEventListener('click',()=>goStep(b.dataset.nextStep)));qsa('[data-prev-step]').forEach(b=>b.addEventListener('click',()=>goStep(b.dataset.prevStep)));progress.forEach(b=>b.addEventListener('click',()=>goStep(b.dataset.goStep)));
        const triggerUpdate=()=>{
            const trigger=qs('input[name="trigger"]:checked',createForm)?.value||'comment';const goal=qs('input[name="goal"]:checked',createForm)?.value||'comment_dm';
            const goalSection=qs('#goalSection',createForm), publicWrap=qs('#publicReplyWrap',createForm), privateWrap=qs('#privateReplyWrap',createForm), mediaStep=qs('#mediaStep',createForm);
            const isDirect=trigger==='direct_message', isPublic=trigger==='comment'&&goal==='comment_public';
            const execution=qs('#executionModeField',createForm); if(execution) execution.value=isDirect?'local':'zernio';
            const gateCard=qs('#followGateCard',createForm), gate=qs('#followGateEnabled',createForm);
            if(gateCard) gateCard.hidden=!(trigger==='comment' && !isPublic);
            if(gate && (trigger!=='comment' || isPublic)) gate.checked=false;
            if(gate?.checked){ const follower=qs('[name=audience_follower_status]',createForm); const unknown=qs('[name=audience_unknown]',createForm); if(follower) follower.value='follower'; if(unknown) unknown.value='verify'; }
            if(goalSection)goalSection.hidden=trigger!=='comment'; if(publicWrap)publicWrap.hidden=isDirect||!isPublic; if(privateWrap)privateWrap.hidden=isPublic;
            if(mediaStep){mediaStep.hidden=isDirect;mediaStep.style.opacity='';} if(isDirect){qs('#targetTypeField',createForm).value='';qs('#targetIdField',createForm).value='';}else{qs('#targetTypeField',createForm).value=trigger==='story_reply'?'story':'post';}
            qs('#targetTitle',createForm).textContent=trigger==='story_reply'?'کدام استوری؟':trigger==='direct_message'?'محتوای هدف لازم نیست':'کدام پست؟';
            qs('#targetDescription',createForm).textContent=isDirect?'برای پیام مستقیم فقط حساب اینستاگرام را انتخاب کن؛ پست یا استوری لازم نیست.':'محتوا را تصویری انتخاب کن؛ هیچ شناسه‌ای لازم نیست.';
            qs('#messageLabel',createForm).textContent=isDirect?'پیام مستقیم':isPublic?'پیام خصوصی نیاز نیست':'پیام خصوصی';
            qs('#reviewTrigger',createForm).textContent=trigger==='story_reply'?'پاسخ به استوری':trigger==='direct_message'?'پیام مستقیم':'نظر روی پست'; qs('#reviewAction',createForm).textContent=isPublic?'پاسخ عمومی':'پاسخ خصوصی';
            qs('#flowTrigger',createForm).textContent=trigger==='story_reply'?'پاسخ استوری':trigger==='direct_message'?'پیام مستقیم':'نظر روی پست'; qs('#flowAction',createForm).textContent=isPublic?'پاسخ عمومی':'پاسخ خصوصی';
            qsa('[data-message-composer]',createForm).forEach(c=>{c.dataset.automationTrigger=trigger;c.dispatchEvent(new CustomEvent('automationtriggerchange'));});
            updateTargetText();
        };
        const updateTargetText=()=>{const trigger=qs('input[name="trigger"]:checked',createForm)?.value;const label=trigger==='direct_message'?'بدون هدف محتوا':(qs('#selectedMediaLabel',createForm)?.textContent||'همه محتوا');qs('#flowTarget',createForm).textContent=label;qs('#reviewTarget',createForm).textContent=label;};
        const mediaGrid=qs('#automationMediaGrid',createForm);const renderMedia=items=>{mediaGrid.innerHTML=items.length?items.map(item=>`<button type="button" class="media-card-mini" data-media-id="${escapeHtml(item.id)}" data-media-type="${escapeHtml(item.mediaType||'IMAGE')}" data-media-label="${escapeHtml(item.label||'محتوا')}" data-media-caption="${escapeHtml(item.caption||'')}"><img src="${escapeHtml(item.thumbnailUrl||'/img/media-placeholder.svg')}" alt=""><div class="media-mini-overlay"><span>${escapeHtml(item.label||'محتوا')}</span></div><p>${escapeHtml(item.caption||'بدون توضیح')}</p></button>`).join(''):`<div class="empty-state span-full"><div class="empty-icon">▧</div><strong>محتوایی پیدا نشد</strong><p>حساب، دسترسی یا فیلتر جست‌وجو را بررسی کن.</p></div>`;qsa('[data-media-id]',mediaGrid).forEach(card=>card.addEventListener('click',()=>{qsa('[data-media-id]',mediaGrid).forEach(x=>x.classList.remove('selected'));card.classList.add('selected');qs('#targetIdField',createForm).value=card.dataset.mediaId;qs('#selectedMediaLabel',createForm).textContent=card.dataset.mediaLabel;qs('#selectedMediaMeta',createForm).textContent=card.dataset.mediaCaption||'محتوای انتخاب‌شده';updateTargetText();}));};
        const fetchMedia=async()=>{const account=qs('input[name="instagram_account_id"]:checked',createForm)?.value;const trigger=qs('input[name="trigger"]:checked',createForm)?.value;if(!account||trigger==='direct_message'){mediaGrid.innerHTML='<div class="empty-state span-full"><div class="empty-icon">↗</div><strong>هدف محتوایی لازم نیست</strong><p>این نوع اتوماسیون به محتوای خاصی وابسته نیست.</p></div>';return;}mediaGrid.innerHTML='<div class="empty-state span-full"><div class="spinner"></div><strong>در حال بارگذاری</strong><p>محتوای حساب در حال دریافت است.</p></div>';try{const type=trigger==='story_reply'?'stories':'posts';const res=await fetch(`/automations/media?account_id=${encodeURIComponent(account)}&type=${type}&limit=40`,{headers:{Accept:'application/json'}});const data=await res.json();if(!res.ok||!data.ok)throw new Error(data.message||'دریافت محتوا انجام نشد.');mediaItems=data.items||[];renderMedia(mediaItems);}catch(e){mediaGrid.innerHTML=`<div class="empty-state span-full"><div class="empty-icon">!</div><strong>دریافت محتوا انجام نشد</strong><p>${escapeHtml(e.message||'مشکل در دریافت محتوا.')}</p><button type="button" class="secondary-action" data-media-retry>تلاش دوباره</button></div>`;qs('[data-media-retry]',mediaGrid)?.addEventListener('click',fetchMedia);}};
        qsa('input[name="instagram_account_id"]',createForm).forEach(i=>i.addEventListener('change',()=>{const l=i.closest('.account-choice');qsa('.account-choice',createForm).forEach(x=>x.classList.remove('selected'));l?.classList.add('selected');qs('#selectedAccountCaption',createForm).textContent=l?.querySelector('.account-copy strong')?.textContent||'انتخاب شده';fetchMedia();}));
        qsa('[data-button-builder]',createForm).forEach(builder=>{loadBuilderWorkflows(builder);});
        qsa('input[name="trigger"],input[name="goal"]',createForm).forEach(i=>i.addEventListener('change',triggerUpdate));
        qs('#refreshMedia',createForm)?.addEventListener('click',fetchMedia);qs('#selectAllMedia',createForm)?.addEventListener('click',()=>{qs('#targetIdField',createForm).value='';qsa('[data-media-id]',mediaGrid).forEach(c=>c.classList.remove('selected'));qs('#selectedMediaLabel',createForm).textContent='همه محتوا';qs('#selectedMediaMeta',createForm).textContent='روی همه محتواهای این حساب اجرا شود.';updateTargetText();});
        qs('#mediaSearch',createForm)?.addEventListener('input',e=>{const q=e.target.value.trim().toLowerCase();renderMedia(mediaItems.filter(i=>(i.caption||'').toLowerCase().includes(q)||(i.label||'').toLowerCase().includes(q)));});
        qsa('[data-preset]').forEach(p=>p.addEventListener('click',()=>{const map={"comment-dm":['comment','comment_dm','پاسخ خودکار کامنت'],"comment-public":['comment','comment_public','پاسخ عمومی کامنت'],"story-dm":['story_reply',null,'پاسخ به استوری'],"dm-reply":['direct_message',null,'پاسخ به دایرکت']};const cfg=map[p.dataset.preset];if(!cfg)return;qs(`input[name="trigger"][value="${cfg[0]}"]`,createForm).checked=true;if(cfg[1])qs(`input[name="goal"][value="${cfg[1]}"]`,createForm).checked=true;if(cfg[2])qs('input[name="name"]',createForm).value=cfg[2];triggerUpdate();goStep(2);}));
        createForm.addEventListener('submit',()=>{qs('#privateReplyMirror',createForm).value=qs('#privateReply',createForm)?.value||'';qs('#quickRepliesJson',createForm).value=JSON.stringify(serializeRepeater('quick'));qs('#buttonsJson',createForm).value=JSON.stringify(serializeButtonBuilder(qs('[data-button-builder]',createForm)));qs('#templateJson',createForm).value=qs('#templateBuilder',createForm)?.value||'';const t=qs('input[name="trigger"]:checked',createForm)?.value;if(t==='direct_message')qs('#targetIdField',createForm).value='';});
        const pmsg=qs('#privateReply',createForm);pmsg?.addEventListener('input',()=>qs('#previewReply',createForm).textContent=pmsg.value||'متن پاسخ اینجا نمایش داده می‌شود.');
        const pub=qs('#publicReply',createForm);pub?.addEventListener('input',()=>{if(qs('#previewReply',createForm)&&qs('input[name="goal"]:checked',createForm)?.value==='comment_public')qs('#previewReply',createForm).textContent=pub.value||'پاسخ عمومی اینجا نمایش داده می‌شود.';});
        const inc=qs('#previewIncoming',createForm);qs('#keywordsWrap input',createForm)?.addEventListener('input',e=>{if(e.target.value)inc.textContent=e.target.value.split(',')[0].trim();});
        const syncGatePreview=()=>{const msg=qs('#followGateMessage',createForm)?.value||'لطفاً ابتدا صفحه را دنبال کنید و سپس روی «بررسی کردم» بزنید.';const label=qs('#followGateButtonLabel',createForm)?.value||'بررسی کردم';const pm=qs('#followGatePreviewMessage',createForm);const pb=qs('#followGatePreviewButton',createForm);if(pm)pm.textContent=msg;if(pb)pb.textContent=label;};
        qsa('#followGateMessage,#followGateButtonLabel,#followGateNotFollowingMessage',createForm).forEach(el=>el.addEventListener('input',syncGatePreview));
        qs('#followGateEnabled',createForm)?.addEventListener('change',triggerUpdate);
        qsa('[data-button-builder] input,[data-button-builder] select',createForm).forEach(el=>el.addEventListener('input',()=>{const pm=qs('#previewReply',createForm); if(pm) pm.textContent=qs('#privateReply',createForm)?.value||'متن پاسخ اینجا نمایش داده می‌شود.';}));
        triggerUpdate();syncGatePreview();const selected=qs('input[name="instagram_account_id"]:checked',createForm);if(selected){qs('#selectedAccountCaption',createForm).textContent=selected.closest('.account-choice')?.querySelector('.account-copy strong')?.textContent||'انتخاب شده';fetchMedia();}
    }

    // Automation edit
    const editForm=qs('#automationEditForm');
    if(editForm){
        editForm.addEventListener('submit',()=>{qs('#editQuickJson',editForm).value=JSON.stringify(serializeRepeater('quick'));qs('#editButtonsJson',editForm).value=JSON.stringify(serializeButtonBuilder(qs('[data-button-builder]',editForm)));const tpl=qs('#editTemplateInput',editForm)?.value||'';qs('#editTemplateJson',editForm).value=tpl;const msg=qs('#editMessage',editForm)?.value||'';const mirror=qs('#editMessageMirror',editForm);if(mirror)mirror.value=msg;});
        qs('#editMessage',editForm)?.addEventListener('input',()=>{qs('#editPreview',editForm).textContent=qs('#editMessage',editForm).value||'متن پاسخ اینجا نمایش داده می‌شود.';});
        qsa('[name="link_preview"]',editForm).forEach(el=>el.addEventListener('change',()=>qs('#editLinkPreview',editForm).value=el.value==='1'?'1':'0'));
        const editTrigger=editForm.dataset.trigger||''; const editPublic=editForm.dataset.public==='1'; const editGate=qs('#editFollowGateEnabled',editForm); const editGateCard=qs('#editFollowGateCard',editForm);
        if(qs('#editExecutionMode',editForm)) qs('#editExecutionMode',editForm).value=['comment','story_reply'].includes(editTrigger)?'zernio':'local';
        const syncEditGate=()=>{if(editGateCard)editGateCard.hidden=!(editTrigger==='comment'&&!editPublic); if(editGate && editGate.checked){qs('[name="audience_follower_status"]',editForm)?.setAttribute('value','follower'); const follower=qs('[name="audience_follower_status"]',editForm); if(follower) follower.value='follower'; const unknown=qs('[name="audience_unknown"]',editForm); if(unknown) unknown.value='verify'; const msg=qs('#editFollowGateMessage',editForm)?.value||'';const label=qs('#editFollowGateButtonLabel',editForm)?.value||'بررسی کردم';if(qs('#editFollowGatePreviewMessage',editForm))qs('#editFollowGatePreviewMessage',editForm).textContent=msg;if(qs('#editFollowGatePreviewButton',editForm))qs('#editFollowGatePreviewButton',editForm).textContent=label;}};
        editGate?.addEventListener('change',syncEditGate);qsa('#editFollowGateMessage,#editFollowGateButtonLabel,#editFollowGateNotFollowingMessage',editForm).forEach(el=>el.addEventListener('input',syncEditGate));syncEditGate();const editBuilder=qs('[data-button-builder]',editForm);if(editBuilder)loadBuilderWorkflows(editBuilder);
    }

    // Simulator
    qsa('[data-simulate]').forEach(button=>button.addEventListener('click',async()=>{const result=qs(button.dataset.simResult);if(!result)return;result.textContent='در حال بررسی…';const url=`/automations/${encodeURIComponent(button.dataset.simulate)}/simulate`;const form=new FormData();form.append('_token',csrf);form.append('text',qs('#simTextEdit')?.value||'');form.append('trigger','comment');try{const res=await fetch(url,{method:'POST',headers:{'X-CSRF-TOKEN':csrf,Accept:'application/json'},body:form});const data=await res.json();result.textContent=JSON.stringify(data,null,2);}catch(e){result.textContent='اجرای شبیه‌سازی انجام نشد.';}}));

    // Content manager
    const contentForm=qs('#contentCreateForm');
    if(contentForm){
        let contentMedia=[], activeContentType='feed';
        const renderSelectedMedia=()=>{const box=qs('#contentSelectedMedia',contentForm);if(!contentMedia.length){box.innerHTML='<div class="empty-mini">هنوز رسانه‌ای انتخاب نشده.</div>';qs('[data-check="media"]')?.classList.remove('done');return;}qs('[data-check="media"]')?.classList.add('done');box.innerHTML=contentMedia.map((m,i)=>`<div><img src="${escapeHtml(m.url)}" alt=""><button type="button" class="media-remove" data-remove-content-media="${i}">×</button></div>`).join('');qsa('[data-remove-content-media]',box).forEach(btn=>btn.addEventListener('click',()=>{contentMedia.splice(Number(btn.dataset.removeContentMedia),1);renderSelectedMedia();updatePreview();}));qs('#mediaItemsJson',contentForm).value=JSON.stringify(contentMedia);};
        const updatePreview=()=>{const p=qs('#contentPreviewMedia',contentForm),cap=qs('#contentPreviewCaption',contentForm);if(contentMedia[0]){p.innerHTML=contentMedia[0].type==='video'?`<video src="${escapeHtml(contentMedia[0].url)}" controls muted></video>`:`<img src="${escapeHtml(contentMedia[0].url)}" alt="">`;}else p.innerHTML='<div class="preview-media-empty">رسانه را انتخاب کن</div>';cap.textContent=qs('#contentCaption',contentForm)?.value||'کپشن اینجا نمایش داده می‌شود.';};
        qs('[data-content-upload]',contentForm)?.addEventListener('click',()=>qs('#contentMediaUpload',contentForm)?.click());qs('#contentMediaUpload',contentForm)?.addEventListener('change',async e=>{for(const file of [...e.target.files]){if(contentMedia.length>=10)break;const form=new FormData();form.append('file',file,file.name);try{const res=await fetch('/content/media/upload',{method:'POST',headers:{'X-CSRF-TOKEN':csrf,Accept:'application/json'},body:form});const data=await res.json();if(!res.ok||!data.ok)throw new Error(data.message||'بارگذاری انجام نشد.');contentMedia.push({url:data.url,type:(data.contentType||'').startsWith('video/')?'video':'image',thumbnailUrl:data.url,name:data.filename||file.name});renderSelectedMedia();updatePreview();}catch(err){toast(err.message||'بارگذاری انجام نشد.','error');}}e.target.value='';});
        qsa('input[name="content_type"]',contentForm).forEach(input=>input.addEventListener('change',()=>{activeContentType=input.value;const limit=input.value==='carousel'?10:1;if(contentMedia.length>limit){contentMedia=contentMedia.slice(0,limit);renderSelectedMedia();}qsa('.reel-only',contentForm).forEach(x=>x.hidden=input.value!=='reel');qsa('.video-only',contentForm).forEach(x=>x.hidden=!['reel','story'].includes(input.value));updatePreview();}));
        qsa('input[name="publish_mode"]',contentForm).forEach(input=>input.addEventListener('change',()=>{qsa('.publish-mode',contentForm).forEach(x=>x.classList.toggle('active',qs('input',x)?.checked));qs('#scheduleRow',contentForm).hidden=qs('input[name="publish_mode"]:checked',contentForm)?.value!=='scheduled';}));
        qs('#contentCaption',contentForm)?.addEventListener('input',updatePreview);
        qs('[data-audio-load]',contentForm)?.addEventListener('click',async()=>{const account=qs('input[name="account_id"]:checked',contentForm)?.value;if(!account)return;const box=qs('[data-audio-list]',contentForm);box.innerHTML='<div class="empty-mini">در حال دریافت…</div>';try{const res=await fetch(`/content/audio?account_id=${account}&limit=40`,{headers:{Accept:'application/json'}});const data=await res.json();if(!res.ok||!data.ok)throw new Error(data.message||'دریافت صدا انجام نشد.');box.innerHTML=(data.items||[]).map(a=>`<button type="button" class="audio-item" data-audio-id="${escapeHtml(a.id)}"><span class="audio-cover">♪</span><span><strong>${escapeHtml(a.name)}</strong><small>${escapeHtml(a.artist||'')}</small></span></button>`).join('')||'<div class="empty-mini">صدایی پیدا نشد.</div>';qsa('[data-audio-id]',box).forEach(btn=>btn.addEventListener('click',()=>{qsa('[data-audio-id]',box).forEach(x=>x.classList.remove('selected'));btn.classList.add('selected');qs('#audioId',contentForm).value=btn.dataset.audioId;}));}catch(e){box.innerHTML=`<div class="empty-mini">${escapeHtml(e.message||'دریافت صدا انجام نشد.')}</div>`;}});
        qs('[data-timezone-visible]',contentForm)?.addEventListener('input',e=>{qs('#hiddenTimezone',contentForm).value=e.target.value||'Asia/Tehran';}); contentForm.addEventListener('submit',e=>{if(!contentMedia.length){e.preventDefault();toast('حداقل یک رسانه انتخاب کن.','error');document.querySelector('[data-content-upload-zone]')?.scrollIntoView({behavior:'smooth',block:'center'});return;}const tags=(qs('#userTagsText',contentForm)?.value||'').split(/[,\n]+/).map(x=>x.trim().replace(/^@/,'' )).filter(Boolean).map((username,index)=>({username,x:0.5,y:0.5,mediaIndex:index}));qs('#userTagsJson',contentForm).value=JSON.stringify(tags);qs('#mediaItemsJson',contentForm).value=JSON.stringify(contentMedia);});
        qsa('.reel-only',contentForm).forEach(x=>x.hidden=true);renderSelectedMedia();updatePreview();
    }

    // Story insights
    qsa('[data-story-insights]').forEach(button=>button.addEventListener('click',async()=>{const modal=qs('[data-story-modal]'),grid=qs('[data-story-modal-grid]'),title=qs('[data-story-modal-title]'),extra=qs('[data-story-modal-extra]');if(!modal)return;modal.hidden=false;title.textContent='آمار استوری';grid.innerHTML='<div class="insight-box"><span>در حال دریافت…</span></div>';try{const res=await fetch(`/content/story-insights?account_id=${button.dataset.accountId}&story_id=${encodeURIComponent(button.dataset.storyId)}`,{headers:{Accept:'application/json'}});const data=await res.json();if(!res.ok||!data.ok)throw new Error(data.message||'آمار در دسترس نیست.');const d=data.data||{};const rows=[['بازدید',d.views],['دسترسی',d.reach],['پاسخ',d.replies],['اشتراک‌گذاری',d.shares],['بازدید پروفایل',d.profileVisits],['دنبال‌کننده جدید',d.follows]];grid.innerHTML=rows.map(([k,v])=>`<div class="insight-box"><span>${k}</span><strong>${v==null?'—':escapeHtml(v)}</strong></div>`).join('');extra.textContent=`تعاملات کل: ${d.totalInteractions??'—'} • خروج: ${d.exits??'—'} • جلو: ${d.tapsForward??'—'} • عقب: ${d.tapsBack??'—'}`;}catch(e){grid.innerHTML='<div class="empty-state span-full"><strong>آمار در دسترس نیست</strong><p>'+escapeHtml(e.message||'مشکل در دریافت آمار.')+'</p></div>';extra.textContent='';}}));
    qs('[data-story-close]')?.addEventListener('click',()=>qs('[data-story-modal]')?.setAttribute('hidden','')); 
    qsa('[data-story-modal]').forEach(m=>m.addEventListener('click',e=>{if(e.target===m)m.hidden=true;}));

    // Delete content quick action
    qsa('[data-content-delete]').forEach(btn=>btn.addEventListener('click',()=>{const modal=qs('[data-confirm-modal]');if(!modal)return;const title=qs('[data-confirm-title]',modal),text=qs('[data-confirm-text]',modal),submit=qs('[data-confirm-submit]',modal);title.textContent='حذف محتوا';text.textContent='این محتوا از فهرست انتشار حذف خواهد شد. این عملیات قابل بازگشت نیست.';modal.classList.add('is-open');modal.setAttribute('aria-hidden','false');let done=false;const handler=async()=>{if(done)return;done=true;submit.disabled=true;submit.textContent='در حال حذف…';const form=new FormData();form.append('_token',csrf);form.append('_method','DELETE');form.append('post_id',btn.dataset.postId);form.append('account_id',btn.dataset.accountId);try{const res=await fetch('/content',{method:'POST',headers:{'X-CSRF-TOKEN':csrf,Accept:'text/html'},body:form});if(res.ok){toast('محتوا حذف شد.');btn.closest('.media-card')?.animate([{opacity:1,transform:'scale(1)'},{opacity:0,transform:'scale(.96)'}],{duration:180,fill:'forwards'});setTimeout(()=>btn.closest('.media-card')?.remove(),180);}else toast('حذف محتوا انجام نشد.','error');}catch{toast('حذف محتوا انجام نشد.','error');}finally{submit.disabled=false;submit.textContent='حذف';modal.classList.remove('is-open');submit.removeEventListener('click',handler);}};submit.addEventListener('click',handler,{once:true});}));

    // Content engagement and timeline
    qsa('[data-content-like]').forEach(btn=>btn.addEventListener('click',async()=>{
        const active=btn.dataset.liked==='1'; const form=new FormData(); form.append('_token',csrf); form.append('account_id',btn.dataset.accountId); form.append('post_id',btn.dataset.postId); if(active)form.append('_method','DELETE');
        try{const res=await fetch(active?'/content/unlike':'/content/like',{method:'POST',headers:{'X-CSRF-TOKEN':csrf,Accept:'application/json'},body:form});const data=await res.json();if(!res.ok||!data.ok)throw new Error(data.message||'عملیات انجام نشد.');btn.dataset.liked=active?'0':'1';btn.textContent=active?'♡ پسند':'♥ پسند';btn.classList.toggle('is-active',!active);toast(active?'پسندیدن لغو شد.':'محتوا پسندیده شد.');}catch(e){toast(e.message||'عملیات انجام نشد.','error');}
    }));
    qsa('[data-content-timeline]').forEach(btn=>btn.addEventListener('click',async()=>{
        const modal=qs('[data-content-timeline-modal]'); const grid=qs('[data-content-timeline-grid]'); if(!modal||!grid)return; modal.hidden=false; grid.innerHTML='<div class="insight-box"><span>در حال دریافت آمار…</span></div>';
        try{const res=await fetch(`/content/timeline?account_id=${btn.dataset.accountId}&post_id=${encodeURIComponent(btn.dataset.postId)}`,{headers:{Accept:'application/json'}});const data=await res.json();if(!res.ok||!data.ok)throw new Error(data.message||'آمار در دسترس نیست.');const rows=data.data?.timeline||data.data?.data?.timeline||[]; if(!rows.length){grid.innerHTML='<div class="empty-state span-full"><strong>هنوز داده روزانه‌ای موجود نیست</strong><p>برای این محتوا داده عملکردی از منبع آماری دریافت نشد.</p></div>';return;} grid.innerHTML=rows.map(r=>`<div class="insight-box"><span>${escapeHtml(r.date||'')}</span><strong>${Number(r.impressions||0).toLocaleString('fa-IR')}</strong><small>نمایش • دسترسی ${Number(r.reach||0).toLocaleString('fa-IR')} • پسند ${Number(r.likes||0).toLocaleString('fa-IR')} • ذخیره ${Number(r.saves||0).toLocaleString('fa-IR')}</small></div>`).join('');}catch(e){grid.innerHTML=`<div class="empty-state span-full"><strong>آمار محتوا در دسترس نیست</strong><p>${escapeHtml(e.message||'')}</p></div>`;}
    }));
    qs('[data-content-timeline-close]')?.addEventListener('click',()=>qs('[data-content-timeline-modal]')?.setAttribute('hidden',''));
    qsa('[data-content-timeline-modal]').forEach(m=>m.addEventListener('click',e=>{if(e.target===m)m.hidden=true;}));

    // Content filter
    const contentBoard=qs('[data-content-board]');
    if(contentBoard){qsa('[data-content-filter] .filter-chip').forEach(chip=>chip.addEventListener('click',()=>{qsa('[data-content-filter] .filter-chip').forEach(x=>x.classList.remove('active'));chip.classList.add('active');const f=chip.dataset.filter;qsa('[data-content-kind]',contentBoard).forEach(card=>card.style.display=f==='all'||card.dataset.contentKind===f?'':'none');}));}

    // Inbox filtering
    const inboxSearch=qs('#inboxSearch'); inboxSearch?.addEventListener('input',()=>{const q=inboxSearch.value.toLowerCase().trim();qsa('[data-inbox-row]').forEach(row=>row.style.display=!q||row.dataset.search.includes(q)?'':'none');});

    // Account health
    qsa('[data-health-check]').forEach(button=>button.addEventListener('click',async()=>{button.disabled=true;button.textContent='در حال بررسی…';try{const res=await fetch(button.dataset.url,{headers:{Accept:'application/json'}});const data=await res.json();toast(data.ok?'وضعیت اتصال بررسی شد.':'بررسی اتصال انجام نشد.',data.ok?'success':'error');window.location.reload();}catch{toast('بررسی اتصال انجام نشد.','error');}finally{button.disabled=false;button.textContent='بررسی دوباره';}}));

    // Comments manager
    const commentsPage=qs('#commentPostList');
    if(commentsPage){
        const accountSelect=qs('#commentAccountSelect');const commentsList=qs('#commentsList');const title=qs('#commentsTitle');const subtitle=qs('#commentsSubtitle');let selectedPost=null;
        const renderPosts=items=>{commentsPage.innerHTML=items.length?items.map(p=>`<button type="button" class="comment-post-item" data-post-id="${escapeHtml(p.id)}" data-post-caption="${escapeHtml(p.caption||'')}" data-post-image="${escapeHtml(p.thumbnailUrl||p.image||'/img/media-placeholder.svg')}"><span class="comment-post-thumb"><img src="${escapeHtml(p.thumbnailUrl||p.image||'/img/media-placeholder.svg')}" alt=""></span><span><strong>${escapeHtml(p.label||p.type||'محتوا')}</strong><small>${escapeHtml(p.caption||'بدون توضیح')}</small></span></button>`).join(''):'<div class="empty-state"><strong>محتوایی پیدا نشد</strong><p>این حساب محتوایی برای انتخاب ندارد.</p></div>';qsa('[data-post-id]',commentsPage).forEach(b=>b.addEventListener('click',()=>{selectedPost=b.dataset.postId;qsa('[data-post-id]',commentsPage).forEach(x=>x.classList.remove('active'));b.classList.add('active');title.textContent='نظرهای محتوا';subtitle.textContent=b.dataset.postCaption||'پست انتخاب‌شده';fetchComments();}));};
        const fetchPosts=async()=>{commentsPage.innerHTML='<div class="empty-state"><div class="spinner"></div><strong>در حال بارگذاری محتوا</strong></div>';try{const res=await fetch(`/automations/media?account_id=${accountSelect.value}&type=posts&limit=40`,{headers:{Accept:'application/json'}});const data=await res.json();if(!res.ok||!data.ok)throw new Error(data.message||'دریافت محتوا انجام نشد.');renderPosts(data.items||[]);}catch(e){commentsPage.innerHTML=`<div class="empty-state"><strong>دریافت محتوا انجام نشد</strong><p>${escapeHtml(e.message||'')}</p></div>`;}};
        const fetchComments=async()=>{if(!selectedPost)return;commentsList.innerHTML='<div class="empty-state"><div class="spinner"></div><strong>در حال دریافت نظرها</strong></div>';try{const res=await fetch(`/comments/list?account_id=${accountSelect.value}&post_id=${encodeURIComponent(selectedPost)}`,{headers:{Accept:'application/json'}});const data=await res.json();if(!res.ok||!data.ok)throw new Error(data.message||'دریافت نظرها انجام نشد.');const items=data.comments||[];commentsList.innerHTML=items.length?items.map(c=>{const author=c.author?.username||c.username||c.from?.username||'کاربر';const text=c.text||c.message||'';const id=c.id||c.commentId;return `<article class="comment-item" data-comment-row data-search="${escapeHtml((author+' '+text).toLowerCase())}" data-comment-id="${escapeHtml(id)}"><div class="comment-item-head"><span class="comment-avatar">${escapeHtml(author.slice(0,2).toUpperCase())}</span><div><strong>@${escapeHtml(author)}</strong><small>${escapeHtml(c.timestamp||c.createdAt||'')}</small></div></div><div class="comment-item-text">${escapeHtml(text)}</div><div class="comment-actions"><button type="button" data-comment-reply data-comment-id="${escapeHtml(id)}" data-author="@${escapeHtml(author)}">پاسخ</button><button type="button" data-comment-action="like" data-comment-id="${escapeHtml(id)}">♡ پسند</button><button type="button" data-comment-action="hide" data-comment-id="${escapeHtml(id)}">مخفی</button><button type="button" class="soft-danger" data-comment-action="delete" data-comment-id="${escapeHtml(id)}">حذف</button></div></article>`;}).join(''):'<div class="empty-state"><div class="empty-icon">◎</div><strong>هنوز نظری نیست</strong><p>با ثبت نظر جدید، اینجا نمایش داده می‌شود.</p></div>';bindCommentActions();}catch(e){commentsList.innerHTML=`<div class="empty-state"><strong>دریافت نظرها انجام نشد</strong><p>${escapeHtml(e.message||'')}</p></div>`;}};
        const bindCommentActions=()=>{qsa('[data-comment-reply]',commentsList).forEach(b=>b.addEventListener('click',()=>{const modal=qs('[data-comment-reply-modal]');modal.hidden=false;qs('[data-comment-reply-title]').textContent=`پاسخ به ${b.dataset.author}`;qs('#replyAccountId').value=accountSelect.value;qs('#replyPostId').value=selectedPost;qs('#replyCommentId').value=b.dataset.commentId;qs('#replyMode').value='public';qsa('[data-reply-mode]').forEach(x=>x.classList.toggle('active',x.dataset.replyMode==='public'));qs('#replyInteractive').hidden=true;qs('#replyMessage').focus();}));qsa('[data-comment-action]',commentsList).forEach(b=>b.addEventListener('click',async()=>{if(b.dataset.commentAction==='delete'&&!confirm('این نظر حذف شود؟'))return;const routes={like:'/comments/like',hide:'/comments/hide',delete:'/comments/delete'};const form=new FormData();form.append('_token',csrf);form.append('account_id',accountSelect.value);form.append('post_id',selectedPost);form.append('comment_id',b.dataset.commentId);if(b.dataset.commentAction==='delete')form.append('_method','DELETE');try{const res=await fetch(routes[b.dataset.commentAction],{method:b.dataset.commentAction==='delete'?'POST':'POST',headers:{'X-CSRF-TOKEN':csrf,Accept:'application/json'},body:form});const data=await res.json();if(!res.ok||!data.ok)throw new Error(data.message||'عملیات انجام نشد.');toast('عملیات انجام شد.');if(b.dataset.commentAction==='delete')b.closest('[data-comment-row]')?.remove();}catch(e){toast(e.message||'عملیات انجام نشد.','error');}}));};
        accountSelect?.addEventListener('change',fetchPosts);qs('#commentsSearch')?.addEventListener('input',e=>{const q=e.target.value.toLowerCase().trim();qsa('[data-comment-row]',commentsList).forEach(r=>r.style.display=!q||r.dataset.search.includes(q)?'':'none');});fetchPosts();
        qsa('[data-reply-mode]').forEach(tab=>tab.addEventListener('click',()=>{qsa('[data-reply-mode]').forEach(x=>x.classList.toggle('active',x===tab));const privateMode=tab.dataset.replyMode==='private';qs('#replyMode').value=privateMode?'private':'public';qs('#replyInteractive').hidden=!privateMode;}));
        qs('#commentReplyForm')?.addEventListener('submit',async e=>{e.preventDefault();const f=new FormData();f.append('_token',csrf);f.append('account_id',qs('#replyAccountId').value);f.append('post_id',qs('#replyPostId').value);f.append('comment_id',qs('#replyCommentId').value);f.append('message',qs('#replyMessage').value);const quick=(qs('#replyQuickReplies')?.value||'').split('|').map(x=>x.trim()).filter(Boolean).slice(0,13).map(title=>({content_type:'text',title}));const buttons=(qs('#replyButtons')?.value||'').split(',').map(x=>x.trim()).filter(Boolean).slice(0,3).map(part=>{const [title,url]=part.split('|').map(x=>x.trim());return {type:url?'url':'postback',title,url: url||undefined,payload:title};});f.append('quick_replies_json',JSON.stringify(quick));f.append('buttons_json',JSON.stringify(buttons));const privateMode=qs('#replyMode')?.value==='private';try{const res=await fetch(privateMode?'/comments/private-reply':'/comments/reply',{method:'POST',headers:{'X-CSRF-TOKEN':csrf,Accept:'application/json'},body:f});const data=await res.json();if(!res.ok||!data.ok)throw new Error(data.message||'پاسخ ارسال نشد.');toast(privateMode?'پیام خصوصی ارسال شد.':'پاسخ عمومی ارسال شد.');qs('[data-comment-reply-modal]').hidden=true;qs('#replyMessage').value='';qs('#replyQuickReplies').value='';qs('#replyButtons').value='';}catch(err){toast(err.message||'پاسخ ارسال نشد.','error');}});
        qsa('[data-close-comment-modal]').forEach(b=>b.addEventListener('click',()=>qs('[data-comment-reply-modal]').hidden=true));
    }

    // Inbox reactions
    qsa('[data-react-message]').forEach(button=>button.addEventListener('click',async()=>{try{const form=new FormData();form.append('_token',csrf);form.append('emoji',button.dataset.emoji||'❤️');const res=await fetch(button.dataset.url,{method:'POST',headers:{'X-CSRF-TOKEN':csrf,Accept:'application/json'},body:form});const data=await res.json();if(!res.ok||!data.ok)throw new Error(data.message||'واکنش ثبت نشد.');button.textContent='♥';toast('واکنش ثبت شد.');}catch(e){toast(e.message||'واکنش ثبت نشد.','error');}}));

})();
