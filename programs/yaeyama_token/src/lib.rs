use anchor_lang::prelude::*;
use anchor_spl::token::{self, Mint, MintTo, Token, TokenAccount, Transfer};

declare_id!("5PAP6AwioCRco33xoFEDSojU6tVbiRg5Bgt8gz5MoTJd");

#[program]
pub mod yaeyama_token {
    use super::*;

    /// YAE Mintを初期化
    pub fn initialize(ctx: Context<Initialize>) -> Result<()> {
        msg!("YAE Mint created: {}", ctx.accounts.mint.key());
        Ok(())
    }

    /// トークンを発行
    pub fn mint_tokens(ctx: Context<MintTokens>, amount: u64) -> Result<()> {
        let cpi_ctx = CpiContext::new(
            ctx.accounts.token_program.to_account_info(),
            MintTo {
                mint: ctx.accounts.mint.to_account_info(),
                to: ctx.accounts.token_account.to_account_info(),
                authority: ctx.accounts.authority.to_account_info(),
            },
        );
        token::mint_to(cpi_ctx, amount)?;
        msg!("Minted {} YAE (raw: {})", amount / 1_000_000, amount);
        Ok(())
    }

    /// トークンを転送
    pub fn transfer_tokens(ctx: Context<TransferTokens>, amount: u64) -> Result<()> {
        let cpi_ctx = CpiContext::new(
            ctx.accounts.token_program.to_account_info(),
            Transfer {
                from: ctx.accounts.from_token_account.to_account_info(),
                to: ctx.accounts.to_token_account.to_account_info(),
                authority: ctx.accounts.authority.to_account_info(),
            },
        );
        token::transfer(cpi_ctx, amount)?;
        msg!("Transferred {} YAE (raw: {})", amount / 1_000_000, amount);
        Ok(())
    }

    /// 店舗を登録
    pub fn register_merchant(
        ctx: Context<RegisterMerchant>,
        name: String,
        cashback_rate: u8,
    ) -> Result<()> {
        require!(cashback_rate <= 100, ErrorCode::InvalidCashbackRate);
        let merchant = &mut ctx.accounts.merchant;
        merchant.authority = ctx.accounts.authority.key();
        merchant.name = name.clone();
        merchant.cashback_rate = cashback_rate;
        merchant.total_sales = 0;
        merchant.is_active = true;
        msg!("Merchant registered: {} (cashback: {}%)", name, cashback_rate);
        Ok(())
    }

    /// QR決済処理
    pub fn process_payment(ctx: Context<ProcessPayment>, amount: u64) -> Result<()> {
        require!(ctx.accounts.merchant.is_active, ErrorCode::MerchantInactive);
        require!(amount > 0, ErrorCode::InvalidAmount);

        // お客さん → 店舗へ転送
        let cpi_ctx = CpiContext::new(
            ctx.accounts.token_program.to_account_info(),
            Transfer {
                from: ctx.accounts.customer_token_account.to_account_info(),
                to: ctx.accounts.merchant_token_account.to_account_info(),
                authority: ctx.accounts.customer.to_account_info(),
            },
        );
        token::transfer(cpi_ctx, amount)?;

        // 売上を記録
        let merchant = &mut ctx.accounts.merchant;
        merchant.total_sales += amount;

        msg!(
            "Payment: {} YAE -> {} (total sales: {})",
            amount / 1_000_000,
            merchant.name,
            merchant.total_sales / 1_000_000
        );
        Ok(())
    }
}

#[derive(Accounts)]
pub struct Initialize<'info> {
    #[account(
        init,
        payer = payer,
        mint::decimals = 6,
        mint::authority = payer,
    )]
    pub mint: Account<'info, Mint>,
    #[account(mut)]
    pub payer: Signer<'info>,
    pub token_program: Program<'info, Token>,
    pub system_program: Program<'info, System>,
    pub rent: Sysvar<'info, Rent>,
}

#[derive(Accounts)]
pub struct MintTokens<'info> {
    #[account(mut)]
    pub mint: Account<'info, Mint>,
    #[account(mut)]
    pub token_account: Account<'info, TokenAccount>,
    pub authority: Signer<'info>,
    pub token_program: Program<'info, Token>,
}

#[derive(Accounts)]
pub struct TransferTokens<'info> {
    #[account(mut)]
    pub from_token_account: Account<'info, TokenAccount>,
    #[account(mut)]
    pub to_token_account: Account<'info, TokenAccount>,
    pub authority: Signer<'info>,
    pub token_program: Program<'info, Token>,
}

#[derive(Accounts)]
#[instruction(name: String)]
pub struct RegisterMerchant<'info> {
    #[account(
        init,
        payer = authority,
        space = 8 + 32 + 4 + 50 + 1 + 8 + 1,
        seeds = [b"merchant", authority.key().as_ref()],
        bump
    )]
    pub merchant: Account<'info, MerchantAccount>,
    #[account(mut)]
    pub authority: Signer<'info>,
    pub system_program: Program<'info, System>,
}

#[derive(Accounts)]
pub struct ProcessPayment<'info> {
    #[account(mut, seeds = [b"merchant", merchant.authority.as_ref()], bump)]
    pub merchant: Account<'info, MerchantAccount>,
    #[account(mut)]
    pub customer_token_account: Account<'info, TokenAccount>,
    #[account(mut)]
    pub merchant_token_account: Account<'info, TokenAccount>,
    pub customer: Signer<'info>,
    pub token_program: Program<'info, Token>,
}

#[account]
pub struct MerchantAccount {
    pub authority: Pubkey,
    pub name: String,
    pub cashback_rate: u8,
    pub total_sales: u64,
    pub is_active: bool,
}

#[error_code]
pub enum ErrorCode {
    #[msg("Merchant is inactive")]
    MerchantInactive,
    #[msg("Invalid cashback rate (0-100)")]
    InvalidCashbackRate,
    #[msg("Amount must be greater than 0")]
    InvalidAmount,
}