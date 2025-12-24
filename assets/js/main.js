// 配置信息
const API_BASE_URL = './api'; // API 基础路径

// 预设金额
const PRESET_AMOUNTS = [1, 5, 10, 20, 50, 100];

// 当前选中的金额
let selectedAmount = 0;

// 页面加载完成后初始化
document.addEventListener('DOMContentLoaded', function() {
    initPresetAmounts();
    initEventListeners();
});

/**
 * 初始化预设金额按钮
 */
function initPresetAmounts() {
    const container = document.getElementById('presetAmounts');
    container.innerHTML = '';

    PRESET_AMOUNTS.forEach(amount => {
        const button = document.createElement('button');
        button.className = 'amount-btn';
        button.textContent = `¥${amount}`;
        button.dataset.amount = amount;
        button.onclick = function() {
            selectAmount(amount);
        };
        container.appendChild(button);
    });
}

/**
 * 初始化事件监听
 */
function initEventListeners() {
    // 自定义金额输入
    const customInput = document.getElementById('customAmount');
    customInput.addEventListener('input', function() {
        const value = parseFloat(this.value) || 0;
        if (value > 0) {
            clearAmountSelection();
            selectedAmount = value;
        }
    });

    // 提交按钮
    const submitBtn = document.getElementById('submitBtn');
    submitBtn.addEventListener('click', handleSubmit);
}

/**
 * 选择预设金额
 */
function selectAmount(amount) {
    // 清除之前的选择
    clearAmountSelection();

    // 设置当前金额
    selectedAmount = amount;

    // 高亮当前按钮
    const buttons = document.querySelectorAll('.amount-btn');
    buttons.forEach(btn => {
        if (parseFloat(btn.dataset.amount) === amount) {
            btn.classList.add('active');
        }
    });

    // 清空自定义输入
    document.getElementById('customAmount').value = '';
}

/**
 * 清除金额选择
 */
function clearAmountSelection() {
    const buttons = document.querySelectorAll('.amount-btn');
    buttons.forEach(btn => btn.classList.remove('active'));
}

/**
 * 显示错误信息
 */
function showError(message) {
    const errorDiv = document.getElementById('errorMessage');
    errorDiv.textContent = message;
    errorDiv.classList.add('show');

    setTimeout(() => {
        errorDiv.classList.remove('show');
    }, 5000);
}

/**
 * 设置提交按钮状态
 */
function setSubmitButtonLoading(loading) {
    const btn = document.getElementById('submitBtn');
    if (loading) {
        btn.classList.add('loading');
        btn.disabled = true;
    } else {
        btn.classList.remove('loading');
        btn.disabled = false;
    }
}

/**
 * 处理提交
 */
async function handleSubmit() {
    // 获取金额
    const customAmount = parseFloat(document.getElementById('customAmount').value) || 0;
    const amount = customAmount > 0 ? customAmount : selectedAmount;

    // 验证金额
    if (amount <= 0) {
        showError('请选择或输入打赏金额');
        return;
    }

    if (amount < 0.01) {
        showError('打赏金额不能小于 0.01 元');
        return;
    }

    if (amount > 9999.99) {
        showError('打赏金额不能大于 9999.99 元');
        return;
    }

    // 获取留言
    const message = document.getElementById('rewardMessage').value.trim();

    // 显示加载状态
    setSubmitButtonLoading(true);

    try {
        // 调用创建订单接口
        const response = await fetch(`${API_BASE_URL}/create_order.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                amount: amount,
                message: message
            })
        });

        const result = await response.json();

        if (result.code === 200) {
            // 保存订单号到 localStorage
            localStorage.setItem('current_order', result.data.order_no);

            // 跳转到支付页面
            window.location.href = result.data.redirect_url;
        } else {
            showError(result.message || '创建订单失败，请重试');
            setSubmitButtonLoading(false);
        }
    } catch (error) {
        console.error('提交失败:', error);
        showError('网络错误，请检查连接后重试');
        setSubmitButtonLoading(false);
    }
}

/**
 * 格式化金额
 */
function formatAmount(amount) {
    return parseFloat(amount).toFixed(2);
}

/**
 * 格式化时间
 */
function formatTime(timeStr) {
    if (!timeStr) return '';
    const date = new Date(timeStr);
    return date.toLocaleString('zh-CN');
}
