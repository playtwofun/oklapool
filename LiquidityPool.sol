// SPDX-License-Identifier: MIT
pragma solidity ^0.8.20;

/**
 * @title LiquidityPool
 * @notice Simple, transparent ETH liquidity pool for Robinhood Chain (Chain ID 4663 / Testnet 46630).
 *
 *  Mechanism:
 *  - User deposits native ETH -> receives LP shares proportional to their pool ownership.
 *  - First depositor: shares = amount (1:1). Later depositors: shares = amount * totalShares / poolBalance.
 *  - Withdrawal burns shares and returns the proportional ETH: amount = shares * poolBalance / totalShares.
 *  - All funds stay in this contract. Owner can ONLY pause/unpause and transfer ownership.
 *    Owner CANNOT touch user funds. 100% non-custodial.
 *
 *  Deploy: open https://remix.ethereum.org -> paste this file -> compile (0.8.20+) ->
 *  deploy with "Injected Provider" (MetaMask on Robinhood Chain). Done. One file, no imports.
 */
contract LiquidityPool {
    // ---- LP share metadata (ERC20-style, informational) ----
    string public constant name = "HoodFi LP Share";
    string public constant symbol = "HF-LP";
    uint8  public constant decimals = 18;

    // ---- State ----
    address public owner;
    bool    public paused;

    uint256 public totalShares;                      // total LP shares in existence
    mapping(address => uint256) public sharesOf;     // user => LP shares

    uint256 private _locked = 1;                     // reentrancy guard

    // ---- Events ----
    event Deposited(address indexed user, uint256 amount, uint256 sharesMinted);
    event Withdrawn(address indexed user, uint256 amount, uint256 sharesBurned);
    event Paused(address indexed by);
    event Unpaused(address indexed by);
    event OwnershipTransferred(address indexed previousOwner, address indexed newOwner);

    // ---- Modifiers ----
    modifier onlyOwner() {
        require(msg.sender == owner, "POOL: not owner");
        _;
    }
    modifier nonReentrant() {
        require(_locked == 1, "POOL: reentrant call");
        _locked = 2;
        _;
        _locked = 1;
    }
    modifier whenNotPaused() {
        require(!paused, "POOL: paused");
        _;
    }

    constructor() {
        owner = msg.sender;
        emit OwnershipTransferred(address(0), msg.sender);
    }

    /// @notice Sending ETH directly to the contract also deposits it.
    receive() external payable whenNotPaused nonReentrant {
        _deposit();
    }

    /// @notice Deposit ETH, receive LP shares.
    function deposit() external payable whenNotPaused nonReentrant {
        _deposit();
    }

    function _deposit() internal {
        uint256 amount = msg.value;
        require(amount > 0, "POOL: zero amount");

        uint256 supply  = totalShares;
        uint256 balance = address(this).balance - amount; // balance BEFORE this deposit

        uint256 shares = (supply == 0 || balance == 0)
            ? amount                              // bootstrap: 1 wei = 1 share
            : (amount * supply) / balance;        // proportional to pool ownership

        require(shares > 0, "POOL: deposit too small");

        sharesOf[msg.sender] += shares;
        totalShares = supply + shares;

        emit Deposited(msg.sender, amount, shares);
    }

    /// @notice Burn `shares` LP shares and receive the proportional ETH back.
    function withdraw(uint256 shares) external nonReentrant {
        require(shares > 0, "POOL: zero shares");
        require(sharesOf[msg.sender] >= shares, "POOL: insufficient shares");

        uint256 supply  = totalShares;
        uint256 balance = address(this).balance;
        uint256 amount  = (shares * balance) / supply;

        sharesOf[msg.sender] -= shares;
        totalShares = supply - shares;

        (bool ok, ) = payable(msg.sender).call{value: amount}("");
        require(ok, "POOL: transfer failed");

        emit Withdrawn(msg.sender, amount, shares);
    }

    /// @notice Withdraw your entire position in one call.
    function withdrawAll() external nonReentrant {
        uint256 shares = sharesOf[msg.sender];
        require(shares > 0, "POOL: nothing to withdraw");

        uint256 supply  = totalShares;
        uint256 balance = address(this).balance;
        uint256 amount  = (shares * balance) / supply;

        sharesOf[msg.sender] = 0;
        totalShares = supply - shares;

        (bool ok, ) = payable(msg.sender).call{value: amount}("");
        require(ok, "POOL: transfer failed");

        emit Withdrawn(msg.sender, amount, shares);
    }

    // ---- Views ----

    /// @notice Total ETH held by the pool.
    function totalLiquidity() external view returns (uint256) {
        return address(this).balance;
    }

    /// @notice How many shares a deposit of `amount` wei would mint right now.
    function previewDeposit(uint256 amount) external view returns (uint256) {
        uint256 supply  = totalShares;
        uint256 balance = address(this).balance;
        if (supply == 0 || balance == 0) return amount;
        return (amount * supply) / balance;
    }

    /// @notice Current ETH value of a user's shares.
    function valueOf(address user) external view returns (uint256) {
        uint256 supply = totalShares;
        if (supply == 0) return 0;
        return (sharesOf[user] * address(this).balance) / supply;
    }

    /// @notice One-call pool snapshot for the frontend.
    function getPoolInfo(address user) external view returns (
        uint256 liquidity,
        uint256 shares,
        uint256 myShares,
        uint256 myValue,
        bool    isPaused,
        address contractOwner
    ) {
        liquidity = address(this).balance;
        shares = totalShares;
        myShares = sharesOf[user];
        myValue = shares == 0 ? 0 : (myShares * liquidity) / shares;
        isPaused = paused;
        contractOwner = owner;
    }

    // ---- Admin (cannot move funds) ----

    function pause() external onlyOwner {
        paused = true;
        emit Paused(msg.sender);
    }

    function unpause() external onlyOwner {
        paused = false;
        emit Unpaused(msg.sender);
    }

    function transferOwnership(address newOwner) external onlyOwner {
        require(newOwner != address(0), "POOL: zero address");
        emit OwnershipTransferred(owner, newOwner);
        owner = newOwner;
    }
}
